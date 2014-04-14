all: run_chroot

run_chroot: run_chroot.c
	cc run_chroot.c -o run_chroot
	@echo "set the file 'run_chroot' to suid root"
