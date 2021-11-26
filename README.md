# taskw-scripts

Used for fixing up taskwarrior task entries.

## Usage

`task add "741099: Add an image: set a minimum width for image recommendations | https://gerrit.wikimedia.org/r/c/mediawiki/extensions/GrowthExperiments/+/741099"`

`task add https://phabricator.wikimedia.org/T296508`

In both cases, the data is parsed, `phab` and `gerrit` UDAs populated, annotations added with link, etc.
