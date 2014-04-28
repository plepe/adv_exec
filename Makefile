all: run_chroot prepare_chroot

run_chroot: run_chroot.c
	cc run_chroot.c -o run_chroot
	@echo "set the file 'run_chroot' to suid root"

prepare_chroot: prepare_chroot.c
	cc prepare_chroot.c -o prepare_chroot
	@echo "set the file 'prepare_chroot' to suid root"
