<?php
/**
 * Removed in 2.3.0
 *
 * This file previously registered filters that attempted to exempt the
 * webhook path from Wordfence login security and recorded raw request
 * headers in transients. Neither hook is consumed by Wordfence, and a
 * security plugin integration must never loosen the host's protections.
 * Pull mode (signed feed over HTTPS) needs no firewall exemption at all.
 *
 * The file is kept only so an in-place upgrade over an older copy does not
 * leave a stale class definition behind; it is excluded from release zips
 * and can be deleted from the repository.
 *
 * @package DriveHR
 * @deprecated 2.3.0 No replacement.
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}
