#include <sys/types.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/mount.h>
#include <sys/stat.h>

char *final_path(char *chroot_path, char *path) {
  char *ret;

  ret = (char*)malloc(strlen(chroot_path) + strlen(path) + 2);
  strcpy(ret, chroot_path);
  strcat(ret, "/");
  strcat(ret, path);

  return ret;
}

#define MOUNT_MAX 16
char *mounts[MOUNT_MAX];
int mounts_count = 0;
#define RSYNC_MAX 16
char *rsync_src[RSYNC_MAX];
char *rsync_dest[RSYNC_MAX];
int rsync_count = 0;
char *chroot_path;

void cleanup() {
  int c = 0;
  char *buf;

  for(c = 0; c < mounts_count; c++) {
    printf("umount %s\n", mounts[c]);
    umount(mounts[c]);

    free(mounts[c]);
  }

  for(c = 0; c < rsync_count; c++) {
    buf = (char*)malloc(32 + strlen(rsync_src[c]) + strlen(rsync_dest[c]));
    sprintf(buf, "rsync -a \"%s\" \"%s\"", rsync_dest[c], rsync_src[c]);

    printf("%s\n", buf);
    system(buf);
  }

  buf = (char*)malloc(32 + strlen(chroot_path));
  sprintf(buf, "rm -rf \"%s\"", chroot_path);
  printf("%s\n", buf);
  system(buf);
}

// from http://stackoverflow.com/questions/2336242/recursive-mkdir-system-call-on-unix
static void mkdir_parents(const char *dir) {
  char tmp[256];
  char *p = NULL;
  size_t len;

  snprintf(tmp, sizeof(tmp),"%s",dir);
  len = strlen(tmp);
  if(tmp[len - 1] == '/')
    tmp[len - 1] = 0;
  for(p = tmp + 1; *p; p++)
    if(*p == '/') {
      *p = 0;
      mkdir(tmp, S_IRWXU);
      *p = '/';
    }
  mkdir(tmp, S_IRWXU);
}

int main(int argc, char *argv[]) {
  int c;
  char r[1024];
  int r_length;
  int err;
  FILE *stdin;
  char cmd;
  char *src;
  char *dest;
  char *final_dest;
  char *buf;

  if(argc < 2) {
    printf("Usage: prepare_chroot [options] PATH\n");
    exit(1);
  }
  chroot_path = argv[1];

  if(geteuid() != 0) {
    printf("prepare_chroot is not set to suid root!\n");
    exit(1);
  }

  printf("DIR %s\n", chroot_path);

  stdin = fdopen(0, "r");

  while(fgets(r, 1024, stdin) != NULL) {
    r[strlen(r) - 1] = '\0';
    cmd = r[0];
    src = &r[1];
    dest = strstr(&r[1], "\t");
    if(dest == NULL) {
      final_dest = NULL;
    }
    else {
      dest[0] = '\0';
      dest = &dest[1];
      final_dest = final_path(chroot_path, dest);
    }

    switch(cmd) {
      case 'C':
      case 'R':
	mkdir_parents(final_dest);

	buf = (char*)malloc(32 + strlen(src) + strlen(final_dest));
	sprintf(buf, "rsync -a \"%s\" \"%s\"", src, final_dest);

	printf("%s\n", buf);
	system(buf);

	if(cmd == 'C') {
	  free(buf);
	}
	else if(cmd == 'R') {
	  if(rsync_count >= RSYNC_MAX) {
	    printf("Warning: can't rsync %s=>%s, RSYNC_MAX(%d) reached.\n", src, dest, RSYNC_MAX);
	  }
	  else {
	    rsync_src[rsync_count] = (char*)malloc(strlen(src) + 1);
	    strcpy(rsync_src[rsync_count], src);
	    rsync_dest[rsync_count++] = final_dest;
	  }
	}

	break;

      case 'M':
	printf("mount %s => %s\n", src, dest);
	mkdir_parents(final_dest);
	if(err = mount(src, final_dest, NULL, MS_BIND, NULL)) {
	  printf("Error mounting %s to %s: %i (%s)\n", src, final_dest, err, strerror(err));
	  cleanup();
	  exit(1);
	}

	if(mounts_count >= MOUNT_MAX) {
	  printf("Error mounting %s; max. %d mounts possible.\n", src, MOUNT_MAX);
	  cleanup();
	  exit(1);
	}

	mounts[mounts_count++] = final_dest;
	break;

      default:
        printf("Invalid command %c\n", r[0]);
	free(final_dest);
    }
  }

  cleanup();
  exit(0);
}
