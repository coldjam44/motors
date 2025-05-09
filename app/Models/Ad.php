<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'category_id', 'country_id', 'city_id','kilometer',
        'title', 'description', 'price', 'main_image','phone_number', 'status','car_model','address'
    ];

    public function user()
    {
        return $this->belongsTo(Userauth::class, 'user_id');
    }
  
  public function views()
{
    return $this->hasMany(AdView::class, 'ad_id');
}


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function images()
    {
        return $this->hasMany(AdImage::class);
    }

    public function fieldValues()
    {
        return $this->hasMany(AdFieldValue::class, 'ad_id');
    }

    public function subImages()
    {
        return $this->hasMany(AdImage::class, 'ad_id');
    }
  
  public function adViews()
{
    return $this->hasMany(AdView::class, 'ad_id');
}


}
