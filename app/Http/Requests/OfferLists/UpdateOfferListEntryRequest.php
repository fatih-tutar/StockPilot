<?php

namespace App\Http\Requests\OfferLists;

use App\Models\OfferListEntry;

class UpdateOfferListEntryRequest extends StoreOfferListEntryRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('offer_list_entry');

        return $entry instanceof OfferListEntry
            && ($this->user()?->can('update', $entry) ?? false);
    }
}
