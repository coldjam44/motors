<?php
// Model: CarModel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarModel extends Model
{
    use HasFactory;

    // تحديد الجدول الذي يرتبط به هذا النموذج
    protected $table = 'car_models';

    // الحقول القابلة للتعديل
    protected $fillable = ['category_field_id', 'value_ar', 'value_en'];

    // العلاقة مع الحقل "Make"
    public function field()
    {
        return $this->belongsTo(CategoryField::class, 'category_field_id'); // تغيير الـ foreign key إلى category_field_id
    }
}
