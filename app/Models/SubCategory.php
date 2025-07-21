<?php

namespace App\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'sub_categories';

    protected $fillable = [
        'name',
        'ref',
        'slug',
        'image',
        'description',
        'status',
        'id_category'
    ];

    //Relations
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class, 'id_category');
    }

    //Functions
    public function updateService(array $data): Model|Builder
    {
        return tap($this)->update($data);
    }
    public function product()
    {
        return $this->hasMany(Product::class, 'id_sub_category');
    }
}
