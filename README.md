# MailChimp Plugin

The **MailChimp** Plugin is for [Grav CMS](http://github.com/getgrav/grav). It creates a form action step permitting the signing up of members to a MailChimp email list.

## Description

Adds MailChimp subscribe form action support.

## Installation

Downloads are available on the [Releases](../../releases) page. Once downloaded and extracted, copy the `mailchimp` directory to your Grav installation's `user/plugins` directory.

### Install from GPM

Installation is available through the GPM. Install it from your site's admin panel or from the terminal:

```bash
$ bin/gpm install mailchimp
```

### Install with Composer

This plugin is available via Packagist.org.

```bash
# install without dev dependencies in a production-type environment.
cd user/plugins
mkdir mailchimp
composer require aaronhipple/grav-plugin-mailchimp --update-no-dev

# install with dev dependencies in a development- or CI-type environment.
cd user/plugins
mkdir mailchimp
composer require aaronhipple/grav-plugin-mailchimp
```

## Usage

Configure the plugin with your MailChimp API key, either in `user/config/plugins/mailchimp.yaml` or using the admin plugin.

Set up the form like so in your form's frontmatter

```yaml
form:
    name: subscribe
    fields:
        -
            name: email
            label: Email
            placeholder: 'Enter your email address'
            type: email
            validate:
                required: true
        - 
            name: my_custom_field
            label: My Custom Field
            placeholder: 'A Custom Field'
            type: text
        -
            name: news_letter
            type: checkbox
            label: 'Yes I would like to receive updated news and information'
    buttons:
        -
            type: submit
            value: Submit
        -
            type: reset
            value: Reset
    process:
        - mailchimp:
            required_fields: [news_letter]
            lists: [1234567, abcdefg]
            field_mappings:
                mailchimpMergeField: my_custom_field
```
