#include <sys/types.h>
#include <unistd.h>
#include <stdio.h>

int main(int argc, char *argv[]) {
  chdir(argv[1]);
  chroot(argv[1]);
  seteuid(getuid());
  printf("executing %s\n", argv[2]);
  execv(argv[2], &argv[3]);
}
