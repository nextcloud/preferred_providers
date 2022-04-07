
app_name=$(notdir $(CURDIR))
project_directory=$(CURDIR)/../$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts
appstore_package_name=$(appstore_build_directory)/$(app_name)

all: clean appstore


# Removes the appstore build
.PHONY: clean
clean:
	rm -rf ./build

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore:
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_build_directory)
	tar cvzf $(appstore_package_name).tar.gz \
		--exclude-vcs \
		$(project_directory)/appinfo \
		$(project_directory)/css \
		$(project_directory)/img \
		$(project_directory)/js \
		$(project_directory)/lib \
		$(project_directory)/templates
