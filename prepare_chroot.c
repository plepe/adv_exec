#include <sys/types.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/mount.h>

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

void cleanup() {
  int c = 0;

  for(c = 0; c < mounts_count; c++) {
    printf("umount %s\n", mounts[c]);
    umount(mounts[c]);

    free(mounts[c]);
  }
}

int main(int argc, char *argv[]) {
  int c;
  char *chroot_path;
  char r[1024];
  int r_length;
  char *p;
  int err;
  FILE *stdin;

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

    switch(r[0]) {
      case 'M':
	p = final_path(chroot_path, &r[1]);

	printf("mount %s => %s\n", &r[1], p);
	mkdir(p);
	if(err = mount(&r[1], p, NULL, MS_BIND, NULL)) {
	  printf("Error mounting %s to %s: %i (%s)\n", &r[1], p, err, strerror(err));
	  cleanup();
	  exit(1);
	}

	if(mounts_count >= MOUNT_MAX) {
	  printf("Error mounting %s; max. %d mounts possible.\n", &r[1], MOUNT_MAX);
	  cleanup();
	  exit(1);
	}

	mounts[mounts_count++] = p;
	break;

      default:
        printf("Invalid command %c\n", r[0]);
    }
  }

  cleanup();
  exit(0);
}
