<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalCompany extends Model
{
    // Kasutame välist andmebaasi ühendust
    protected $connection = 'external_companies';
    
    // Tabeli nimi välises andmebaasis
    protected $table = 'companies';
    
    // Väljad, mida saame kasutada
    protected $fillable = [
        'name',
        'regcode',
        'kmcode',
        'started',
        'ended',
    ];
    
    // Kui välises andmebaasis pole timestamps välju
    public $timestamps = false;
    
    // Otsingu meetod ettevõtte nime järgi
    public static function searchByName($query, $limit = 10)
    {
        return static::where('name', 'LIKE', "%{$query}%")
            ->orWhere('regcode', 'LIKE', "%{$query}%")
            ->limit($limit)
            ->get(['id', 'name', 'regcode', 'kmcode', 'started', 'ended']);
    }
    
    // Meetod täiendavate andmete saamiseks seotud tabelitest
    public function getAdditionalData()
    {
        $companyId = $this->id;
        
        // Pärime emailid
        $emails = \DB::connection('external_companies')
            ->table('company_emails')
            ->where('company_id', $companyId)
            ->pluck('email')
            ->toArray();
            
        // Pärime telefonid
        $phones = \DB::connection('external_companies')
            ->table('company_phones')
            ->where('company_id', $companyId)
            ->pluck('phone')
            ->toArray();
            
        // Pärime veebilehed
        $websites = \DB::connection('external_companies')
            ->table('company_www')
            ->where('company_id', $companyId)
            ->pluck('www')
            ->toArray();
            
        return [
            'emails' => $emails,
            'phones' => $phones,
            'websites' => $websites,
        ];
    }
}
