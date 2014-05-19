all: run_chroot prepare_chroot

chstat: chstat_run_chroot chstat_prepare_chroot

run_chroot: run_chroot.c
	cc run_chroot.c -o run_chroot
	@echo "run 'make chstat' as root"

prepare_chroot: prepare_chroot.c
	cc prepare_chroot.c -o prepare_chroot
	@echo "run 'make chstat' as root"

chstat_run_chroot: run_chroot
	chown root: run_chroot
	chmod u+s run_chroot

chstat_prepare_chroot: prepare_chroot
	chown root: prepare_chroot
	chmod u+s prepare_chroot
