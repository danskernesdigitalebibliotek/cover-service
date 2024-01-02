<?php
/**
 * @file
 * Service for updating data from 'Chandos' tsv file.
 */

namespace App\Service\VendorService\Chandos;

/**
 * Class ChandosVendorService.
 *
 * @deprecated
 */
class ChandosVendorService
{
    public const VENDOR_ID = 10;

    protected string $vendorArchiveDir = 'Chandos';
    protected string $vendorArchiveName = 'chandos.load.tsv';
}
