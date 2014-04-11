#include <sys/types.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>

int main(int argc, char *argv[]) {
  if(argc < 2) {
    printf("Usage: run_chroot PATH EXECUTABLE PARAMETERS\n");
    exit(1);
  }

  if(geteuid() != 0) {
    printf("run_chroot is not set to suid root!\n");
    exit(1);
  }

  chdir(argv[1]);
  chroot(argv[1]);
  seteuid(getuid());
  printf("executing %s\n", argv[2]);
  execvp(argv[2], &argv[2]);
}
