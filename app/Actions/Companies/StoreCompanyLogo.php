<?php

namespace App\Actions\Companies;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class StoreCompanyLogo
{
    /**
     * Logos are public: they appear on the company's profile and listings.
     */
    public function handle(UploadedFile $logo): string
    {
        return $logo->store('company-logos', 'public')
            ?: throw new RuntimeException('The company logo could not be stored.');
    }
}
