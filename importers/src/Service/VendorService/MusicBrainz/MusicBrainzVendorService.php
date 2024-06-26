<?php
/**
 * @file
 * Service for updating data from 'Musicbrainz' tsv file.
 */

namespace App\Service\VendorService\MusicBrainz;

/**
 * Class MusicBrainzVendorService.
 *
 * @deprecated
 */
class MusicBrainzVendorService
{
    public const VENDOR_ID = 9;

    protected string $vendorArchiveDir = 'MusicBrainz';
    protected string $vendorArchiveName = 'mb.covers.tsv';
}
