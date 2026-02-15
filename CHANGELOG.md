## 1.9.0 - 2026-02-15
feat: Cookie upload via admintools (#152)

Adds a form to admintools that accepts Netscape-format cookies with a
configurable TTL (15/30/60/120 min). Cookies are saved with expiration
timestamps in the filename and automatically cleaned up by flag_check.sh.

- Cookie files are write-only (0200) to prevent Apache from reading them
- Cookies directory permissions updated so Apache can write but not list
- Fixed PHP timezone (was UTC, now matches container EST)

## 1.8.5 - 2026-02-14
ci: Pipeline Optimizations (#151)

* ci: Remove unnecessary reverse proxy test.

* ci: Remove unused Build ID parameter

This was defined at some point but never implemented in the pipeline.
Removed to reduce confusion, and clean up the parameter list. May re-add later.

* ci: Add intelligent image caching based on job.

PR Builds will be forced to fully rebuild.
Branch Builds will use caching for speed!
Dangling images are now cleaned up when doing fresh builds.

Should significantly reduce build times during branch updates for faster
testing, while ensuring PR builds get clean dependency updates.

* ci: Add optional pause functionality.

With the previously unused pause parameter, it's now implemented
so, when enabled, allows stepping through each stage of the pipeline
for deeper troubleshooting capabilities. Also updated the container URL.

* ci: Fix extra curly brace

* build: Optimize image layers

- Reduced image size by adding dnf clean all
- Combined run layers to speed up build and reduce total layers (size).
- Moved deno install to start of build process, optimize layer cache.

* test: Fix testing pipeline

## 1.8.4 - 2026-02-11
fix: Adds deno to path. Required for yt-dlp to use it. (#150)

## 1.8.3 - 2026-02-11
fix: Several bug fixes, upgrade major os edition.

* chore: update base image to Fedora 43

* fix: Fixes inability to solve js challenges

* fix: Fixes a problem where members-only videos will download themselves 10 times every time.

## 1.8.2 - 2025-07-18
test: Allows unit test to try a few times before failing, allowing time for server to come up.

## 1.8.1 - 2025-07-17
ci: Fix url in unit test

## 1.8.0 - 2025-07-11
feat: Apple touch icon (#141)

* feat: Add touch icon.

* feat: Host touch icon within node.

* test: Write unit test

* fix: Put env variable in string

* fix: Fixes syntax for env variables

* fix: Add comma to test

* fix: Fix port with wrong context

* fix: Remove port?

## 1.7.1 - 2025-06-14
fix: Fix support for shorts (#140)

## 1.7.0 - 2025-06-14
feat: Yt dlp version check

* fix: Expand flag system

* feat: Add yt-dlp Version Check

* fix: Set default values in case dlp has no check file

* feat: Add check button

* fix: Adjust alert text

* fix: Move dlp check down on admintools

* chore: Adjust text on scan now button

* ci: Add dlp-override for integration testing the new feature.

* ci: Add pipeline for testing yt-dlp update feature.

* ci: Fix testing error on .lower of nonetype.

* ci: Fix indentation issue, add dlponly flag to speed up Jenkins

* ci: fix Jenkinsfile positional argument in test

* ci: Wait for webpage to load properly before starting.

* ci: Increase timeout

* ci: Test to figure out why this is failing

* ci: Test to ensure the page is giving 200 before trying the next step...

* ci: Try to catch the exception

* ci: Add progress print statements.

## 1.6.0 - 2024-11-06
feat: Add build date (#134)

* feat: Add build date

* feat: Adjust build date

## 1.5.1 - 2024-11-06
fix: Update yt-dlp explicitly (#132)

## 1.5.0 - 2024-10-19
feat: Add metadata to ASMRchive player

* feat: Add metadata to ASMRchive player

* fix: Handle escaping double quotes

## 1.4.2 - 2024-10-19
chore: Update to Fedora 40 (#129)

## 1.4.1 - 2024-08-27
fix: Fix member playlist for no entries channels (#126)

## 1.4.0 - 2024-08-26
feat: Add members playlist link to admintools (#124)

## 1.3.0 - 2024-08-21
feat: Add version to admintools (#122)

## 1.2.4 - 2024-08-21
fix: Handles youtube block with re-queue (#120)

* fix: Handles youtube block with re-queue

* fix: remove nooverwrites rule which shouldn't have been left in.

## 1.2.3 - 2024-07-31
fix: Fix queue when newline in channel file

* fix: Fix website queue

* fix: Filter blank lines in python end

## 1.2.2 - 2024-07-23
fix: Update yt image

* fix: Change yt image

* fix: Fix broken url check function

* fix: Fix broken images in player when using default thumbnail.

## 1.2.1 - 2024-07-23
fix: Change yt image (#115)

## 1.2.0 - 2024-07-23
feat: YouTube Button

* feat: Add youtube image

* feat: Add button

* feat: Add functionality to youtube button

* test: Add test for youtube button

## 1.1.0 - 2024-06-26
Download Button (#112)

* feat: Add download button

* test: Add download integration test

* test: Move default download location

* test: fix logic

## 1.0.1 - 2024-06-25
ci: Versioning (#111)

* ci: Add versioning logic

