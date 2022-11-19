# Pinned Because

This is pre-release software.

## What?

This is a Composer plugin which adds one command:

    composer pin symfony/http-foundation --because 'We need this specific version or EVERYTHING WILL BREAK!'

Given this command, the plugin will do two things:

1. Find the specific version of the package in question within the lock file
   and require that within the project's `composer.json` file.
2. Add the `--because` message to the `composer.json` file's `extra` section.

This plugin is not configurable. It does not have any other opinions. It only
moves the locked version of the file to your project `composer.json` and
documents why.

The `--because` clause is REQUIRED and ALWAYS WILL BE. :-)

## How?

Install:

    composer require getdkan/pinned-because-plugin

Then do the thing:

    composer pin package/name --because 'description of why'

## What's next?

Intercept `require` command and tell the user why they shouldn't require a different version.
