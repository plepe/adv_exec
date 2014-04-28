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

int main(int argc, char *argv[]) {
  int c;
  extern char *optarg;
  extern int optind;
  char *chroot_path;

#define MOUNT_MAX 16
  char *mounts[MOUNT_MAX];
  int mounts_ind=0;

  while ((c = getopt(argc, argv, "m:")) != -1)
    switch(c) {
      case 'm':
	mounts[mounts_ind++] = optarg;
	break;

      default:
        break;
    }

  if(argc < 1 + optind) {
    printf("Usage: prepare_chroot [options] PATH\n");
    exit(1);
  }
  chroot_path = argv[optind];

  if(geteuid() != 0) {
    printf("prepare_chroot is not set to suid root!\n");
    exit(1);
  }

  printf("DIR %s\n", chroot_path);

  // mount directories
  for(c = 0; c < mounts_ind; c++) {
    char *p;
    int err;
    p = final_path(chroot_path, mounts[c]);

    printf("mount %s => %s\n", mounts[c], p);
    mkdir(p);
    if(err = mount(mounts[c], p, NULL, MS_BIND, NULL)) {
      printf("Error mounting %s to %s: %i (%s)\n", mounts[c], p, err, strerror(err));
      exit(0);
    }

    free(p);
  }
}
