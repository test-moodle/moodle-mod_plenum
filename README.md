# Plenary meeting #

The Plenary meeting activity module organizes online and in person
meetings following traditional rules of parliamentary procedure. It may
be used as a tool to teach skills to conduct a meeting or to structure
and administer faculty, staff, student or other organizational meetings.

Users are granted the privileges to speak, make motions, vote or chair
meetings through Moodle capabilities and roles. The activity maintains
a record of motions to provide meeting minutes. User actions are logged
in Moodle log system.

The activity can be used with an in person event or as an online
event. It has a integration with Deft response block to allow audio and
video to be shared based on whether the user is recognized to speak in
the activity. Other web conference or media servers also can be used as
long as users can be managed by them manually. A minimal integration is
provided for Jitsi meet.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code and ZIP file for block Deft
response. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this
directory to

    {your/moodle/dirroot}/mod/plenum

and place code for block Deft response in

    {your/moodle/dirroot}/blocks/deft

Afterwards, log in to your Moodle site as an admin and 1. go to _Site
administration > Notifications_ to complete the installation.  2. Adjust
subplugin settings to accommodate variations in parliamentary procedure.
3. Install Block deft response or other conferencing integration
dependency for online meeting support.

## License ##

2023 onward Daniel Thies <dethies@gmail.com>

This program is free software: you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the
Free Software Foundation, either version 3 of the License, or (at your
option) any later version.

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
for more details.

You should have received a copy of the GNU General Public License along
with this program.  If not, see <https://www.gnu.org/licenses/>.
