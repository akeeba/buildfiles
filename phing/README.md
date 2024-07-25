# Common Phing Script

Build automation for Joomla! extensions, WordPress plugins, and AWF applications.

This script is designed to be used with the [Phing](http://phing.info) build automation tool. The tasks in this file are designed for publishing software on Joomla! sites which use [Akeeba Release System](http://github.com/akeeba/release-system), or directly on [a GitHub repository's Releases](https://docs.github.com/en/repositories/releasing-projects-on-github/about-releases) using [Akeeba Release Maker](https://github.com/akeeba/releasemaker).

## Setup

### Pre-requisites

Before you begin you need the following directory layout:

* `buildfiles` A working copy of this repository.
* `releasemaker` A working copy of [Akeeba Release Maker](https://github.com/akeeba/releasemaker). Optional, only used
   by the `release` task.
* Your working copy is in a subdirectory at the same level as the aforementioned directories.
* Your Phing script is in your working copy's root.
* You need a `build` folder for additional files necessary to configure the build.

Keep in mind that the `ftpdeploy` task assumes that you're using an SFTP-capable server. Plain FTP (and to a certain extent FTPS) is a vulnerable protocol, thus not supported. It is trivial for an attacker to steal your credentials and compromise your site, or the software contained therein.

### Using with your Phing script

Include it in your own Phing script with:

`<import file="${phing.dir}/../../buildfiles/phing/common.xml" />`

### Build properties

The common Phing script relies on build properties to customise it. A list of all available options and their explanations can be found in the `default.properties` file shipped in this directory.

You must create the `build/build.properties` file in your repository's working copy with your configuration values. This file is meant to be committed to Git, therefore the following privileged parameter values must NOT be included in it:

* s3.access
* s3.private
* s3.bucket
* s3.path
* scp.* (all keys starting with `scp.`)
* release.api.endpoint
* release.api.username
* release.api.password
* release.api.token
* release.update_dir
* github.token

You can store your privileged parameter values in the file `build.properties` _above_ your working copy. Here's how the folder structure would look like:

```
Projects										The root directory of your Git working copies
	+--- buildfiles								This repository
	+--- releasemaker							Akeeba Release Maker
	+--- yourProject							Your project's working copy
	|       +--- build.xml				        Your Phing script
	|       +--- build
	|       |      +--- build.properties		Unprivileged build properties, committed to Git
	|       |      +--- override.properties		Overrides; do NOT commit to Git. Use only for testing / experimentation.
	|       …      …
	+--- build.properties						Privileged build properties, outside the repository, NOT commit to Git.
```

> ℹ️ Privileged property values are, indeed, common among all your projects. The more projects you have using the Common Phing Script the more sense it makes having all this information in just one file. When you rotate your passwords you just need to update a single file. 

#### Temporary overrides

If you need to test something it may not make sense editing your `build.properties` files. Instead, create the file `build/override.properties`. This will override properties defined in the two other files.

> ⚠️ Do NOT commit this file to your Git repository. This is meant to be used for quick tests only. When you are done, transfer your changes into the respective `build.preoperties` files.

#### OS-specific overrides

If you are working across different Operating Systems some of your properties may need different values depending on the OS you are currently using.

This Common Phing Build file supports per-OS overrides for each of the property files:

* `build.OS_NAME.properties` for the two `build.properties` files
* `overrides.OS_NAME.properties` for the `build/overrides.properties` file

The OS_NAME is the name of the Operating System as returned by PHP itself in the `PHP_OS` predefined constant. You can run `phing info` to execute the `info` task in this Common Phing Script to find it out. You will see a line like this:

```
     [echo] Host OS Linux release 5.15.153.1-microsoft-standard-WSL2 version #1 SMP Fri Mar 29 23:14:13 UTC 2024
```

The text between `Host OS` and `release` is the `OS_NAME` you need to use.

> ⚠️ When using WSL (Windows Subsystem for Linux) the OS_NAME is `Linux`, not `WINNT`.

## Internal symlinks

There are many cases where the layout of your repository isn't identical to the layout of the files you want in an installation script. For example, you may want to have all your language files in a separate folder instead of mixed in with your code as Joomla! and WordPress expect them to be.

In other cases, you may want to include libraries maintained in separate repositories, without going through Composer or NPM.

This can all be achieved by using symbolic links (symlinks) to files / folders, or hardlinks to other files.

Symlinks and hardlinks are a sore point for source control systems such as Git. Hardlinks are stored as regular files, which defeats the point of having a hardlink in the first place. Symlinks can accidentally be using absolute paths, so when they are committed by one developer they no longer work on another developer's machine. For this reason, we prefer to NOT commit hard and symbolic links, instead storing a file which defines how to rebuild them. This file is `build/templates/link.php`.

The format of the file is as follows:

```php
<?php
$hardlink_files = [];

$symlink_files = [];

$symlink_folders = [];
```

Each of the arrays is a map of existing files to where the hard/symbolic link will be created.

Let's take the following file for example:

```php
<?php
$symlink_files = [
    '../external_lib/src/FooBar.php' => 'component/backend/lib/FooBar.php',
    'languages/en-GB/backend/com_foobar.ini' => 'component/backend/language/en-GB/com_foobar.ini'
];
```

This will create a symlink named `FooBar.php` under the `component/backend/lib` folder of the working copy of the repository which points to the file `../external_lib/src/FooBar.php` that lives outside the working copy.

Moreover, it will create a symlink named `com_foobar.ini` in the `component/backend/language/en-GB` folder of the working copy, pointing to the file `languages/en-GB/backend/com_foobar.ini` which lives inside the working copy.

Symlinks and hardlinks are created automatically when you run the build commands `git` or `all`. If you want to rebuild the symlinks and hardlinks yourself, run `phing link`.

## Auto-versioning

The default version used for building software is `git`, used as a simple placeholder.

You can override the version in the command line, using `-Dversion=1.2.3` where `1.2.3` is your version number.

If the version is set to the default placeholder (`git`), the build script will try to automatically determine the version it should use. This is done by the AutoVersionTask.

First, it will look for a file called `CHANGELOG`, `CHANGELOG.md`, `CHANGELOG.php`, or `CHANGELOG.txt` in the working copy's root. The contents of this file must be like this:

```php
<?php die; ?>
Something 1.2.3
================================================================================

Changelog items...

Something 1.2.2
================================================================================

Changelog items...
```

The opening die statement may be omitted. The next line may have EITHER the name of the software followed by its version, OR just the version. The version MUST adhere to the [Semantic Versioning](https://semver.org/) specification. It is imperative the latest version is always at the top of the file.

If the changelog file is not present, the build script will look for the latest tag in the repository, and increase the patch level version by one. The tag names in this case MUST adhere to the [Semantic Versioning](https://semver.org/) specification.

If there was neither a changelog, nor a tag which can be used, the fake version `0.0.0` will be used instead.

In any case, the string `-dev`, the date and time, the string `-rev`, and the short Git commit hash will be appended to the version number as version metadata.

## Joomla! components

The Common Phing Script is designed to easily build installation packages for Joomla! components without much fussing around.

Before you begin, you need to have the following folder structure in your repository:

```
Repository Root
   +--- build								Phing build files
   |       +--- templates 					Template XML manifest and version.php files
   +--- component							Component files
   |       +--- backend						Back-end files
   |       |      +--- src                  Namespace root for backend files
   |       +--- frontend					Front-end files
   |       |      +--- src                  Namespace root for frontend files
   |       +--- media						Anything that goes in the media/com_something folder of the site
   |       +--- language					Default language files to be installed (recommended: just en-GB files) 
   |       +--- script.something.php		Joomla! installation script for the PACKAGE
   |       +--- script.com_something.php	Joomla! installation script for the COMPONENT
   +--- modules								Your modules (must be present)
   |       +--- site						Front-end modules (must be present, even if it has no contents)
   |       |      +-- whatever				Files for front-end module mod_whatever...
   |       |      …
   |       +--- admin						Backup-end modules (must be present, even if it has no contents)
   |       |      +-- whatever				Files for back-end module mod_whatever...
   |       |      …
   +--- plugins								Your plugins (must be present, even if it has no contents)
           +--- system						System plugins. Likewise for other plugin types.
           |      +-- whatever				Files for plg_system_whatever...
           …      …
```

Please replace `something` and `com_something` with the name of your extension. For example, if your extension is `com_magicbus` the script files are called `script.magicbus.php` and `script.com_magicbus.php`.

The next thing you need to consider is which of the following use cases best matches yours:

* **You only publish a free of charge version**. Read the Single Target instructions below.
* **You only publish a paid version**. Read the Single Target instructions below.
* **You publish both a free of charge, and a paid version**. Read the Two Targets instructions below.

### Single Target

You need to define the following `<fileset>` IDs:

* **component** The files to include in your component package, relative the the `component` directory
* **package** The files to include in the installation package, relative the the `release` directory

A note about the `package` ID. The build script will create ZIP files following the convention com_something.zip, file_something.zip, pkg_system_whatever.zip, mod_site_whatever.zip, mod_admin_whatever.zip. These are the files you need to reference in your `<fileset>`.

You will need the following XML files in your `build/templates` directory:

* **something.xml** (optional) The Joomla! XML manifest for the component installation ZIP file
* **pkg_something.xml** The Joomla! XML manifest for the package extension type installation ZIP file

Where `something` is the name of your extension.

All these files can have the following replaceable strings in them:
* `##VERSION##`. The current version of the build, either defined in the command line or determined automatically with the auto-versioning rules.
* `##DATE##`. The current build date in the form `YYYY-MM-DD` e.g. `2024-08-01` for August 1st, 2028.

> ℹ️ If the `build/templates/something.xml` file is missing you MUST include the `component/something.xml` file in your repository. In this case, the version number will change as per the XML file auto-versining rules explained later.

Finally, if you want to include a Joomla! installation script you need to use the following naming convention:

* **script.something.php** Joomla! installation script for the Package extension type
* **script.com_something.php** Joomla! installation script for the Component extension type

Where `something` is the name of your extension.

### Two Targets

We refer to free of charge versions as Core and paid versions as Pro (that's the convention we use for our products, hence its use in our build scripts).

You need to define the following `<fileset>` IDs:

* **component-core** The files to include in the component package of the free of charge version, relative the the `component` directory
* **package-core** The files to include in the installation package of the free of charge version, relative the the `release` directory
* **component-pro** The files to include in the component package of the paid version, relative the the `component` directory
* **package-pro** The files to include in the installation package of the paid version, relative the the `release` directory

A note about the `package` ID. The build script will create ZIP files following the convention com_something-core.zip, file_something-core.zip, pkg_system_whatever.zip, mod_site_whatever.zip, mod_admin_whatever.zip. These are the files you need to reference in your `<fileset>`.

IMPORTANT: Even though the com_ and file_ ZIP files have a core/pro suffix the actual Joomla! extension name is com_something and file_something for BOTH the Core and Pro packages. This allows you to upgrade from Core to Pro without messing up the `#__extensions` table's records.

You will need the following XML files in your `build/templates` directory:

* **something_core.xml** The Joomla! XML manifest for the component installation ZIP file of the free of charge version
* **pkg_something_core.xml** The Joomla! XML manifest for the package extension type installation ZIP file of the free of charge version
* **something_pro.xml** The Joomla! XML manifest for the component installation ZIP file of the paid version
* **pkg_something_pro.xml** The Joomla! XML manifest for the package extension type installation ZIP file of the paid version

All these files can have the following replaceable strings in them:
* `##VERSION##`. The current version of the build, either defined in the command line or determined automatically with the auto-versioning rules.
* `##DATE##`. The current build date in the form `YYYY-MM-DD` e.g. `2024-08-01` for August 1st, 2028.
* `##PRO##`. 1 if it's the Pro (paid) version, 0 if it's the Core (free of charge) version.

> ℹ️ When you have a Pro and Core release target you MUST use all four of these files.

Where `something` is the name of your extension.

Please note that the core / pro suffixed XML files are renamed to `something.xml` and `pkg_something.xml` when put inside the respective ZIP files, as per Joomla's conventions.

IMPORTANT: Unlike the refid's the XMl files use an underscore, not a dash, to separate the filename from the core/pro suffix!

Finally, if you want to include Joomla! installation script you need to use the following naming convention:

* **script.something.php** Joomla! installation script for the Package extension type
* **script.com_something.php** Joomla! installation script for the Component extension type

Where `something` is the name of your extension. Please note that BOTH Core and Pro versions use the SAME installation scripts. 

Also note that even though the generated packages are in the format pkg_something-1.2.3-core.zip and
pkg_something-1.2.3-pro.zip BOTH packages have the internal Joomla! extension name pkg_something. This allows you to upgrade from Core to Pro version without messing up the `#__extensions` table's records.

### Including other extensions in your package

If you want to include extensions other than the component, modules and plugins built by the Common Phing Script you will need to override the `component-packages` target. *Do not override any other target involved in package building*. The dependencies must be added BEFORE the `package-pkg` dependency. For example:

`<target name="component-packages" depends="my-stuff,some-other-stuff,package-pkg" />`

The targets `my-stuff` and `some-other-stuff` are supposed to include the additional extensions' installation packages in the `release` folder of the repository. Also remember to change the `package`, `package-core` or `package-pro` `<fileset>`s to include these additional files into your package.

If you need to clean up those files after build you can do something like:
```
<target name="component-packages" depends="my-stuff,some-other-stuff,package-pkg">
	<delete>
		<fileset dir="${dirs.release}">
			<include name="pkg_other.zip" />
			<include name="tpl_whatever.zip" />
		</fileset>
	</delete>
</target>
```

The `<delete>` task will be called by Phing after the `depends` targets have completed, therefore right after the package building is complete. Just make sure you don't remove your freshly built pkg_* files!

### XML file auto-versioning

The XML manifest files for the package, component, plugins, and modules are automatically assigned the build version _as long as_ the current version number does not include the string `git`, `dev`, or `rev` i.e. it's not a development release.

### Language files

Language files need to be stored in the following directories for site relinking (explained later) to work reliably.

Component language files are stored in the following directories (where LANG-CODE is the language code, e.g. `en-GB`, and `com_something` is your component's name): 
* `component/backend/language/LANG-CODE/com_something.sys.ini`
* `component/backend/language/LANG-CODE/com_something.ini`
* `component/frontend/language/LANG-CODE/com_something.ini`

Plugin and module language files are stored in the `language/LANG-CODE` folder of each plugin and module.

### Site relinking

It is very tedious making changes, building a package, install it on a dev site, and see if your changes worked. Instead, things go faster if you can simply create symbolic links (symlinks) in a dev site pointing back to your extension's repository. This is what the `relink` task does.

For the `relink` task to work, your repository layout must be as described in the introduction above. Language files must be in the locations explained above.

You can relink to any local site using:

```php
phing relink -Dsite=/path/to/site/root
```

Relinking works on Linux, macOS, and Windows. We strongly recommend usign Linux, macOS, or WSL. Under Windows, symbolic and hard links tend to be a bit problematic and require elevated permissions to create even within your own user folder. 

### Updating phpStorm

phpStorm has a very useful feature which allows you to define PSR-4 roots. All modern Joomla! extensions (Joomla! 4.0 and later) use PSR-4 for their PHP files.

The problem is that when you switch Git branches phpStorm will "forget" the PSR-4 root definitions. Having them set up in your `composer.json` is an option, but it causes a lot of problems if you are using Composer to pull in third party dependencies into your extension.

Instead, you can use `phing phpstorm` to fix this problem. As long as your repository layout is what explained in the introduction of this chapter, and you have the necessary XML manifest files set up with the `<namespace>` tag, your phpStorm PSR-4 roots will be synced with the extensions you have in your working copy.

## Building installation packages for WordPress plugins

[//]: # (TODO)

## Building installation packages for AWF applications

[//]: # (TODO)

## Releasing software

[//]: # (TODO)

### Changelog and release notes

We always keep a CHANGELOG and Release Notes for our software. Our Phing script is designed to fully support both of these pieces of information, and use them for development and regular releases.

#### CHANGELOG

The CHANGELOG is a short text file which contains one line per change made in a version compared to its immediately previous one.

You may thing that the Git log is the same thing. It is not. The Git log may have multiple commits for each change performed (especially if you don't use squashed commits, or don't always sumbit PRs for changes), and it may include items related to the upkeep of the repository, the documentation etc. Not every Git log item is a CHANGELOG item, hence the need for a separate CHANGELOG.

The `CHANGELOG` file in your working copy's root is expected to have the following format:

```php
<?php die; ?>
Something 1.2.3
================================================================================

Changelog items...

Something 1.2.2
================================================================================

Changelog items...
```

The opening die statement may be omitted. The next line may have EITHER the name of the software followed by its version, OR just the version. The version MUST adhere to the [Semantic Versioning](https://semver.org/) specification. It is imperative the latest version is always at the top of the file.

If the `CHANGELOG` file is not present then Akeeba Release Maker will not know about it, and will not be able to attach it to the end of the release notes. Moreover, it will not be uploaded with development releases, therefore those releases will have no release notes (since the development release's release notes is just the changelog of the latest version).

The changelog item lines are preceded by the following symbols:
* `!` Very important change, or security fix
* `+` New feature
* `-` Removed feature
* `~` Minor change, behaviour change, etc
* `#` Bug fix. Bug fixes can also be assigned a priority:
  * `# [LOW]` A low priority bugfix. Something which could be worked around, just had a minor impact, or was otherwise not incredibly important. 
  * `# [MEDIUM]` A potentially show-stopper issue which only happened in rare circumstances.
  * `# [HIGH]` An issue affecting most if not all users which would have significant impact on reliability, or even cause a hard stop.
  * `!` A security fix. Note that security fixes, while technically still bugs, are not assigned a generic bugfix symbol in the changelog because of their importance.

These symbols have semantic meaning in Akeeba Release System and will be used to render information according to their meaning. Lines without any symbol may be treated as comments.

#### Release notes

Release notes are more detailed than CHANGELOGs. They are meant to convey information which may not be apparent by just reading the CHANGELOG (especially if the users don't have access to the source code repository, or are not technically proficient).

We use release notes to talk about new features and important changes using plain language.

The release notes are stored in a file named `RELEASENOTES.html` or `RELEASENOTES.md`. 

The `RELEASENOTES.html` file is used when releasing software to a site running the Akeeba Release System. The CHANGELOG is automatically appended to the releases notes under a new H3 tag.

The `RELEASENOTES.md` file is used when releasing software directly to GitHub. In this case, the CHANGELOG **IS NOT** appended to the release notes; you have to do it manually.

### Akeeba Release System

[//]: # (TODO)

### GitHub releases

[//]: # (TODO)

### Custom code before and after each release

There are two special tasks defined in this Phing file called `onBeforeRelease` and `onAfterRelease`. By default, both of these tasks are empty.

The `onBeforeRelease` task is executed before the `release` task creates its `release.yaml` / `release.json` file. You can use it to take any actions necessary before deploying a new release.

The `onAfterRelease` task is executed right after the `release` task deletes its `release.yaml` / `release.json` file. You can use it to take any actions necessary after deploying a new release.

## Development releases

Sometimes you want to have your clients test a new build of your software before you release a new version. You can use this Phing script with Akeeba Release System's BleedingEdge category to do that.

First, set up the BleedingEdge categories on your site. If you have a Core and Pro version for your extension each one should get its own BleedingEdge category.

Next up, you need to upload the ZIP files for a development release to your site, so that ARS' BleedingEdge category can see them and list them. This is where the `ftpdeploy` task comes in.

It configuration consists of the following settings in your `build.properties`:

```ini
; SFTP connection information
;; Hostname, e.g. sftp.example.com
scp.host=
;; TCP/IP Port
scp.port=22
;; Your SFTP username, e.g. myhostinguser
scp.username=
;; OPTION 1: PASSWORD AUTHENTICATION. Your SFTP password.
scp.password=
;; OPTION 2: CERTIFICATE AUTHENTICATION. The path to your public key file, e.g. /home/user/.ssh/id_rsa.pub
scp.pubkeyfile=
;; OPTION 2: CERTIFICATE AUTHENTICATION. The path to your private key file, e.g. /home/user/.ssh/id_rsa
scp.privkeyfile=
;; OPTION 2: CERTIFICATE AUTHENTICATION. The password to your private key file. Leave empty if nto password protected.
scp.privkeyfilepassphrase=
; SFTP directory for the ARS repository root
scp.dir=/var/www/html
; SFTP directory for the DocImport public media folder
scp.dir.docs=/var/www/html/media/com_docimport

; SFTP Deploy patterns. Files matching these patterns will be uploaded when doing `phing ftpdeploy`
ftpdeploy.pattern.core=com_example-*-core.zip
ftpdeploy.pattern.pro=com_example-*-pro.zip

; SFTP Deploy paths. These are relative to scp.dir above.
ftpdeploy.path.core=files/dev/examplecore
ftpdeploy.path.pro=files/dev/examplepro
```

Running `phing ftpdeploy` will build your software (just like running `phing git`), then it will use the properties above to upload your dev builds to your site, along with the `CHANGELOG` file.

> ℹ️ The Pro version's dev release will only be uploaded if `build.has_pro` is set to 1.