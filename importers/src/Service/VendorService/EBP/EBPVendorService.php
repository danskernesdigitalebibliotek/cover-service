<?php
/**
 * @file
 * Service for updating data from 'EBP' tsv file.
 */

namespace App\Service\VendorService\EBP;

/**
 * Class EBPVendorService.
 *
 * @deprecated
 */
class EBPVendorService
{
    public const VENDOR_ID = 13;

    protected string $vendorArchiveDir = 'EBP';
    protected string $vendorArchiveName = 'ebp.load.tsv';
}
