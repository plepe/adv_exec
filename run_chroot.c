#include <sys/types.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

int main(int argc, char *argv[]) {
  char *chroot_cwd;

  if(argc < 3) {
    printf("Usage: run_chroot PATH WORKING_DIR EXECUTABLE PARAMETERS\n");
    exit(1);
  }

  if(geteuid() != 0) {
    printf("run_chroot is not set to suid root!\n");
    exit(1);
  }

  // concatenate chroot directory and cwd to build final path
  chroot_cwd = (char*)malloc(strlen(argv[1]) + strlen(argv[2]) + 2);
  strcpy(chroot_cwd, argv[1]);
  strcat(chroot_cwd, "/");
  strcat(chroot_cwd, argv[2]);

  chdir(chroot_cwd);
  chroot(argv[1]);
  seteuid(getuid());
  printf("executing %s\n", argv[3]);
  execvp(argv[3], &argv[3]);
}
