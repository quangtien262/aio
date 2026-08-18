<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'document_type', 'year', 'prefix', 'next_number', 'padding'])]
class AcctDocumentNumberSequence extends Model
{
    protected $table = 'acct_document_number_sequences';
}
