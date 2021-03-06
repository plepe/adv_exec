#include <sys/types.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/mount.h>
#include <sys/stat.h>
#include <stdbool.h>
#include <errno.h>
#include <signal.h>

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

  for(c = mounts_count - 1 ; c >= 0; c--) {
    if(umount(mounts[c]) == -1) {
      printf("Error unmounting %s: %s(%d)\n", mounts[c], strerror(errno), errno);
      fflush(stdout);
    }

    free(mounts[c]);
  }

  for(c = 0; c < rsync_count; c++) {
    buf = (char*)malloc(32 + strlen(rsync_src[c]) + strlen(rsync_dest[c]));
    sprintf(buf, "rsync -a \"%s\" \"%s\"", rsync_dest[c], rsync_src[c]);

    system(buf);
  }

  buf = (char*)malloc(32 + strlen(chroot_path));
  sprintf(buf, "rm -rf \"%s\"", chroot_path);
  system(buf);
}

void cleanup_signal() {
  // wait a second - maybe processes need to die first?
  sleep(1);

  // now cleanup
  cleanup();

  // and exit
  exit(0);
}

// from http://stackoverflow.com/questions/2336242/recursive-mkdir-system-call-on-unix
// final: also create final directory, e.g. mkdir_parents("/tmp/foo/bar", false) will create "/tmp" and "/tmp/foo"; mkdir_parents("/tmp/foo/bar", true) will create "/tmp" and "/tmp/foo" and "/tmp/foo/bar/"
static void mkdir_parents(const char *dir, bool final) {
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
      mkdir(tmp, 0755);
      *p = '/';
    }

  if(final)
    mkdir(tmp, 0755);
}

int main(int argc, char *argv[]) {
  int c;
  char r[1024];
  int r_length;
  int err;
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

  signal(SIGINT, cleanup_signal);
  signal(SIGTERM, cleanup_signal);

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
      case 'c':
	mkdir_parents(final_dest, false);

	buf = (char*)malloc(32 + strlen(src) + strlen(final_dest));
	sprintf(buf, "cp -p \"%s\" \"%s\"", src, final_dest);

	printf("%s\n", buf);
	err = system(buf);

	printf("DONE%d\n", err);
	fflush(stdout);

	free(buf);
	break;

      case 'C':
      case 'R':
	mkdir_parents(final_dest, false);

	buf = (char*)malloc(32 + strlen(src) + strlen(final_dest));
	sprintf(buf, "rsync -a \"%s\" \"%s\"", src, final_dest);

	printf("%s\n", buf);
	err = system(buf);

	printf("DONE%d\n", err);
	fflush(stdout);

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
      case 'm':
	printf("mount %s => %s\n", src, dest);
	mkdir_parents(final_dest, true);
	if(err = mount(src, final_dest, NULL, MS_BIND, NULL)) {
	  printf("Error mounting %s to %s: %i (%s)\n", src, final_dest, err, strerror(err));
	  cleanup();
	  exit(1);
	}

        // re-mount to read-only
	if((cmd == 'm') &&
	   (err = mount(src, final_dest, NULL, MS_BIND|MS_REMOUNT|MS_RDONLY, NULL))) {
	  printf("Error mounting %s to %s: %i (%s)\n", src, final_dest, err, strerror(err));
	  cleanup();
	  exit(1);
	}

	printf("DONE%d\n", err);
	fflush(stdout);

	if(mounts_count >= MOUNT_MAX) {
	  printf("Error mounting %s; max. %d mounts possible.\n", src, MOUNT_MAX);
	  cleanup();
	  exit(1);
	}

	mounts[mounts_count++] = final_dest;
	break;

      case 'Q':
	cleanup();
	printf("DONE\n");
	exit(0);
	break;

      default:
        printf("Invalid command %c\n", r[0]);
	free(final_dest);
    }
  }

  cleanup();
  exit(0);
}
