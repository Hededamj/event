<?php
/**
 * Menu page - redirected to schedule page with menu section.
 * Kept for backward compatibility with bookmarks/cached URLs.
 */
header("Location: ?id=$eventId&page=schedule&section=menu");
exit;
