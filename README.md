# Craft IoT PoC plugin for Craft CMS 3.x

## Summary

This plugin is a proof-of-concept based on Nick Le Guillou's 2018 DotAll presentation, [Building IoT Solutions Using Core CMS Features](http://dotall.com/sessions/building-iot-solutions-using-core-cms-features). It demonstrates how Craft can be used as a headless, IoT platform to store and configure data.

**NOTE: This plugin is intended for experimental and prototying purposes only, and is *not* recommended for production use.**

## Requirements

Craft CMS 3.0.12 or later.

## Optional Prerequisites

* [Element API](https://github.com/craftcms/element-api) version 2.5.4 or later, for accessing data from a front-end application (eg. React, Vue, Angular, etc).
* Account credentials for [Pusher](https://pusher.com), if building a real-time front-end application.

## Installation

To install the plugin, search for "Craft IoT PoC" in the Craft Plugin Store, or install manually using composer.

```
composer require nickfreedom/craft-iot-poc
```

## Using Craft IoT PoC

Read the documentation at [docs/configuration-and-use](docs/configuration-and-use.md).

## Questions, Issues, Feedback

* [Submit a GitHub Issue](https://github.com/nickfreedom/craft-iot-poc-plugin/issues/new).
* [Create a Pull Request](https://github.com/nickfreedom/craft-iot-poc-plugin/compare).
* Send an email to nick.leguillou@gmail.com.
