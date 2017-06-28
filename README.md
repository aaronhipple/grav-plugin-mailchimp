# MailChimp Plugin

The **MailChimp** Plugin is for [Grav CMS](http://github.com/getgrav/grav). It creates a form action step permitting the signing up of members to a MailChimp email list.

## Description

Adds MailChimp subscribe form action support.

## Installation

This plugin is available via Packagist.org.

```bash
composer require aaronhipple/grav-plugin-mailchimp:^0.0.5
```

`gpm` installation is not yet available.

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
    process:
        - mailchimp:
            lists: [1234567, abcdefg]
            field_mappings:
                mailchimpMergeField: my_custom_field
```
