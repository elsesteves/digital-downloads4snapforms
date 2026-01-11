# DigitalDownloads4SnapForms

This is an integration for the SnapForms WordPress plugin that provides a way to offer file downloads on demand from SnapForms submissions.

- Core entry: `digital-downloads4snapforms.php`
- Download Logic: `includes/Download.php`
- Config (external): `wp-content/snapforms/addons/digital-downloads/config.json`

## Overview
This plugin listens to SnapForms submission events and powers a simple two-step flow:

1) A verification email is sent to the requester with a link to the download request.
2) On confirmation, a sucess page will be shown and the file will be downloaded.

Multiple SnapForms forms are supported, each with its own downloadable file and messaging settings defined in an external JSON config file.

## Key Features
- Per-form configuration in a single external JSON file
- Customizable verification/confirmation pages and emails with placeholders
- External config location (safer across plugin updates)
- Graceful error handling with WordPress notices and `WP_Error`

## Requirements
- WordPress
- SnapForms plugin

## How It Works
- On each SnapForms submission (`snapforms.submission.new`), the plugin sends a verification email containing a link.
- The user visits the verification page and clicks the CTA to download the file.
- The submission is marked approved once the file is downloaded.

## Installation
1. Copy this plugin folder into `wp-content/plugins/` and activate it from WordPress admin.
2. Create the external config directory and file:

## Configuration Location
The configuration file lives outside the plugin directory to avoid being overwritten by updates.
- Path: `wp-content/snapforms/addons/digital-downloads/config.json`

The plugin loads and indexes entries by `id_form`.

## Config Schema
Top-level object with a `forms` array. Each entry defines one SnapForms form integration. All keys are strings unless noted.

- `id_form` (string, required): SnapForms internal form ID this config applies to.
- `title` (string, optional): Title shown on the verification/confirmation pages.
- `download` (array): Information regarding the downloadable file.
  - `name` (string): File name.
  - `url` (string): Direct file download URL.
- `pages` (object, optional): HTML content shown on verification and confirmation pages.
  - `verification` (object): Verification page HTML content
    - `message` (string): HTML with placeholders.
    - `button.label` (string)
    - `button.post_click_label` (string)
  - `success` (object): Success page HTML content
    - `message` (string): HTML with placeholders.
- `emails` (object, optional): Verification and confirmation email templates.
  - `verification` (object): Verification email template.
    - `subject` (string)
    - `email_address` (string)
    - `body` (string): HTML with placeholders.

### Example Config
```json

{
  "forms": [
    {
      "id_form": "26",
      "title": "Modern WordPress Forms Checklist",
      "download": {
        "name": "Modern WordPress Forms Checklist",
        "url": "https://example.com/path/to/free_guide.pdf"
      },
      "pages": {
        "verification": {
          "message": " <p>Greetings, {recipient:name},</p><p>Have you requested the Modern WordPress Forms Checklist?</p><p>If so, click the button below, to access it for free.</p><p>We hope you find it useful.</p>",
          "button": {
            "label": "Access Guide",
            "post_click_label": "Accessing Guide... Please wait."
          }
        },
        "success": {
          "message": "<p>The download of your Modern WordPress Forms Checklist to your device should be triggered shortly</p></p>We hope you find it useful.</p>"    
        }
      },
      "emails": {
        "verification": {
          "subject": "Free Guide: Modern WordPress Forms Checklist",
          "email_address": "marketing@snapforms.tech",
          "body": "Greetings, {recipient:name},<br><br>We have received your request for the Modern WordPress Forms Checklist.<br><br>If you'd like to download it, completely free of charge, please access the following link:<br><a href='{vrfy_link}'>{vrfy_link}</a><br><br>Hope to see you soon,<br>The SnapForms Team"
        }
      }
    }
  ]
}
```

## Placeholders
- `{recipient:name}`: Name of the submission recipient
- `{vrfy_link}`: Verification link sent in the first email

## Multiple Forms
Add one entry per SnapForms form to the `forms` array. The plugin indexes these entries internally by `id_form` for quick lookups.

## Error Handling
- API request failures and validation issues surface as WordPress notices and `WP_Error` objects.
- Errors may be logged via `error_log` when applicable.

## Uninstallation
Removing the plugin does not delete the external config directory. To fully remove configuration, delete `wp-content/snapforms/addons/digital-downloads/config.json` manually.

## Developers
- Hook consumed: `snapforms.submission.new` (verification email)
- Frontend actions: `sfdg_download_vrfy`, `sfdg_download_confirm`
- Download Logic: `includes/Download.php`

## Changelog
### 1.0.0
- Initial public release: external config, multi-form support, customizable pages and emails.

## Credits
- Author: [Eduardo Esteves] (https://edluis97.github.io/)
