<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryFieldValue extends Model
{
    use HasFactory;

    protected $fillable = ['category_field_id', 'value_ar', 'value_en'];

    public function field()
    {
        return $this->belongsTo(CategoryField::class);
    }

    public function categoryField()
    {
        return $this->belongsTo(CategoryField::class, 'category_field_id');
    }

    public function carModels()
{
    return $this->hasMany(CarModel::class, 'category_field_id', 'id');
}

}
