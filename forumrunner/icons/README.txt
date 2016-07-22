Forum Runner Icons Directory README
-----------------------------------

If you wish to customize the look of your application, you can modify various
default icons that ship with Forum Runner.

Modifying the Forum/Subforum icons
----------------------------------

If you wish to modify the default forum/subforum icons that Forum Runner
displays to be something more in line with your forum, you can do so easily.

If you want to change the icons for ALL your forums and subforums, simply
create PNG images with the dimensions 64x64 and place them in this directory.
They should be named:

forum-default-new.png: Shown for all forums as the default when there are NEW
  posts in this forum/subforum
forum-default-old.png: Shown for all forums as the default when there are not
  any new posts in this forum/subforum
forum-default-link.png: Shown for all forums as the default when this forum is
  a link forum (to another forum or to a website)

If you want to customize on a per-subforum basis, you will need to know the
forum id of the forum in question.  You can get this through the Forums
setting in the admin control panel.  Once you know the forum id, you can use
the following filename format:

forum-<forum_id>-new.png: Shown for this subforum when there are new posts in
  this forum.  This overrides the default icon above.
forum-<forum_id>-old.png: Shown for this subforum when there are not any new
  new posts in this forum/subforum
forum-<forum_id>-link.png: Shown for this forum if this forum is a link forum

Also note, if you do not have a "-old.png" file specified, it will just use
the "-new.png" file.

If you have any further questions, please see this thread regarding
customizing your forum:

http://www.forumrunner.com/forum/showthread.php?t=346
