<?php

namespace App\Exports;

use App\Models\Common\Payroll;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
class UsersExport implements FromCollection,WithHeadings,ShouldAutoSize
{	
	public function __construct($collection)
    {
        $this->collection = $collection;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->collection;
    }

    public function headings(): array
    {
        return [
            '#',
            'First Name',
            'Last Name',
            'Email',
            'Mobile',
            'Rating',
            'Wallet Amount'
        ];
    }

    
}
