<?php

namespace App\Http\Controllers\Apis;

use App\Models\blog;
use Illuminate\Http\Request;
use App\Http\Trait\GeneralTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use App\Http\Resources\BlogControllerResource;

class BlogController extends Controller
{
    use GeneralTrait;

    public function index()
    {
        $blogs = blog::all();
        $blog_data = BlogControllerResource::collection($blogs);

        return $this->returnData('blogs',$blog_data);

    }

    public function store(Request $request)
    {
        try {
            //$validated = $request->validated();
            $request->validate([
                'title_ar'        => 'required|string|',
                'title_en'        => 'required|string|',
                'description_ar'  => 'required|string',
                'description_en'  => 'required|string',
            ],
            [
                'title_ar.required'        => 'The Arabic title is required.',
                'title_ar.string'          => 'The Arabic title must be a valid string.',

                'title_en.required'        => 'The English title is required.',
                'title_en.string'          => 'The English title must be a valid string.',

                'description_ar.required'  => 'The Arabic description is required.',
                'description_ar.string'    => 'The Arabic description must be a valid string.',

                'description_en.required'  => 'The English description is required.',
                'description_en.string'    => 'The English description must be a valid string.',
            ]);

            $blog = new blog();

            $blog->title_ar= $request->title_ar;
            $blog->title_en= $request->title_en;
            $blog->description_ar = $request->description_ar;
            $blog->description_en = $request->description_en;

            $image=$request->image;
            $imagename=time().'.'.$image->getClientOriginalExtension();
            $request->image->move(public_path('blog'), $imagename);

            $blog->image=$imagename;

            $blog->save();
            return $this->returnData('blog',$blog);


        } catch (\Exception $e) {
           return $this->returnError('E001','error');
        }
    }

    public function update(Request $request, blog $blog)
    {
        try {

            $request->validate([
                'title_ar'        => 'required|string|',
                'title_en'        => 'required|string|',
                'description_ar'  => 'required|string',
                'description_en'  => 'required|string',
            ],
            [
                'title_ar.required'        => 'The Arabic title is required.',
                'title_ar.string'          => 'The Arabic title must be a valid string.',

                'title_en.required'        => 'The English title is required.',
                'title_en.string'          => 'The English title must be a valid string.',

                'description_ar.required'  => 'The Arabic description is required.',
                'description_ar.string'    => 'The Arabic description must be a valid string.',

                'description_en.required'  => 'The English description is required.',
                'description_en.string'    => 'The English description must be a valid string.',
            ]);

            $blogs = blog::findOrFail($request->id);
            $blogs->update([
                'title_ar'=> $request->title_ar,
                'title_en'=> $request->title_en,
                'description_ar'=> $request->description_ar,
                'description_en'=> $request->description_en,
            ]);

            if ($request->hasFile('image')) {
                // Delete the old image if it exists
                $oldImage = public_path('blog/' . $blogs->image);
                if (File::exists($oldImage)) {
                    File::delete($oldImage);
                }

                // Upload the new image
                $image = $request->image;
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('blog'), $imageName);

                // Update the image name in the database
                $blogs->image = $imageName;
            }

            $blogs->save();



           return $this->returnData('blogs',$blogs);

        } catch (\Exception $e) {
           return $this->returnError('E001','error');
        }
    }

    public function destroy($id)
    {
        $blog = blog::findOrFail($id)->delete();
        if(!$blog){
            return $this->returnError('E001','data not found');
        }else{
            return $this->returnSuccessMessage('data deleted');
        }




    }
}

