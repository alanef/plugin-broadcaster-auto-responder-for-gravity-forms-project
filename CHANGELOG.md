# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Removed

- **Template placeholders.** The in-hours and out-of-hours "template placeholders" settings are gone, and a
  feed no longer sends placeholder values. They filled a reply from the form's answers, so whatever a stranger
  typed into a form could be sent, under your business's name, to any number they entered. Broadcaster now
  ignores such values and fills a reply with your business details and opening hours only (BRO-1831). Feeds
  saved with placeholders keep working; their placeholder settings are simply not sent.

### Changed

- **A reply template is checked when you save the feed.** The plugin asks Broadcaster whether each template
  can be sent in reply to a form, and refuses one that needs anything else (such as the contact's name), with
  Broadcaster's reason under the field. If Broadcaster cannot be reached, the feed still saves.

## [1.1.5]

- Baseline. Earlier history is in `broadcaster-auto-responder-for-gravity-forms/readme.txt`.
