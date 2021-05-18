<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Post;
use App\Models\Category;

use Intervention\Image\ImageManagerStatic as InterventionImages;

use Inertia\Inertia;

use App\Models\Image;
use App\Models\User;






use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


use App\Models\Tag;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Symfony\Contracts\Service\Attribute\Required;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user:id,name')
        ->with('categories:slug,name')
        ->orderBy('created_at', 'desc')
        ->paginate(10);
            

        return Inertia::render('Posts/Index', ['posts' => $posts]);

        // // return Inertia::render('Users/Index', [
        // //     'users' => User::paginate('id', 'name', 'email'),
            
        // ]);
 
        
        
    }

    public function create()
    {
        $categories = Category::orderBy('name', 'asc')->get();
        
        
       
        return Inertia::render('Posts/Form', ['categories' => $categories, 'statuses' => Post::STATUSES]);
       
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'title' => [
                'required',
                'string'
            ],
            'body' => [
                'string'
            ],
            'category_ids' => [
                'required',
                'array',
                'min:2',
            ],
            'tags_input' => [
                'string'
            ],
            'image' => [
                'mimes:jpeg,jpg,png,gif|required|max:200000'
            ]
            
        ])->validate();

        $params = $request->all();
        $params['slug'] = Str::slug($params['title']);
        $params['user_id'] = $request->user()->id;
        $params['post_type'] = Post::POST;
        // $params['status'] = Post::DRAFT;

        if ($params['status'] == POST::ACTIVE){
            $params['published_at'] = now();
        }
        
        $tags = explode(',', $params['tags_input']);
        $tagIds = [];
        foreach ($tags as $tag) {
            $itemTag = Tag::where('name', trim($tag))->first();

            if (!$itemTag) {
                $itemTag = Tag::create(['name' => trim($tag), 'slug' => Str::slug(trim($tag))]);
            }

            $tagIds[] = $itemTag->id;
        }

        $post = Post::create($params);
        $post->categories()->attach($params['category_ids']);
        $post->tags()->attach($tagIds);

        if ($request->file('image')) {
            $image = $request->file('image');
            $fileName = $image->getClientOriginalName();
            $folder = Image::UPLOAD_FOLDER;

            $path = $image->storeAs($folder, $fileName, 'public');
            
            if($path){
                $resizedImages = $this->resizeImage($image, $fileName, $folder);
            //    dd($resizedImages);
                $imagesPath = array_merge([
                    'original' => $path,
                ], $resizedImages);

                $post->images()->create($imagesPath);
            }
        }

        return redirect('/posts')->with('message', 'Post created successfully.');
    }

    private function resizeImage($image, $fileName, $folder)
    {
        $resizedImage = [];

        $smallImageFilePath = $folder  . '/small/' . $fileName;
        $size = explode('x', Image::SMALL);
        list($width, $height) = $size;
        $smallImageFile = InterventionImages::make($image)->fit($width, $height)->stream();
        if (Storage::put('public/' . $smallImageFilePath, $smallImageFile)) {
            $resizedImage ['small'] = $smallImageFilePath;
        }

        $mediumImageFilePath = $folder  . '/medium/' . $fileName;
        $size = explode('x', Image::MEDIUM);
        list($width, $height) = $size;
        $mediumImageFile = InterventionImages::make($image)->fit($width, $height)->stream();
        if (Storage::put('public/' . $mediumImageFilePath, $mediumImageFile)) {
            $resizedImage ['medium'] = $mediumImageFilePath;
        }

        $largeImageFilePath = $folder  . '/large/' . $fileName;
        $size = explode('x', Image::LARGE);
        list($width, $height) = $size;
        $largeImageFile = InterventionImages::make($image)->fit($width, $height)->stream();
        if (Storage::put('public/' . $largeImageFilePath, $largeImageFile)) {
            $resizedImage ['large'] = $largeImageFilePath;
        }

        return $resizedImage;
    }

    public function edit($id) {
        $post = Post::findOrFail($id);
        $categories = Category::orderBy('name', 'asc')->get();

        if ($post->user_id != Auth::user()->id)

        {
            return redirect('/posts')->with('message', 'You can not edith this post.');
        }

        return Inertia::render('Posts/Form',[
            'post' => $post,
            'categories' => $categories,
            'statuses' => Post::STATUSES
        ]);

        
    }

    public function update(Request $request, $id)
    {
        // dd($request->all());
        $post = Post::findOrFail($id);
        if ($post->user_id != Auth::user()->id){
            return redirect('/posts')->with('message', 'You can not edith this post.');
        }

        Validator::make($request->all(), [
            'title' => [
                'required',
                'string'
            ],
            'body' => [
                'required',
                'string'
            ],
            'category_ids' => [
                'required',
                'array',
                'min:2',
            ],
            'tags_input' => [
                'string'
            ],
            'image' => [
                'mimes:jpeg,jpg,png,gif|required|max:200000'
            ]
        ])->validate();
        
        $params = $request->all();

        $tags = explode(',', $params['tags_input']);
        $tagIds = [];
        foreach ($tags as $tag) {
            $itemTag = Tag::where('name', trim($tag))->first();

            if (!$itemTag) {
                $itemTag = Tag::create(['name' => trim($tag), 'slug' => Str::slug(trim($tag))]);
            }

            $tagIds[] = $itemTag->id;
        }
        
        $post->update($params);
        $post->categories()->sync($params['category_ids']);
        $post->tags()->sync($tagIds);

        if ($request->file('image')) {
            $image = $request->file('image');
            $fileName = $image->getClientOriginalName();
            $folder = Image::UPLOAD_FOLDER;

            $path = $image->storeAs($folder, $fileName, 'public');
            
            if($path){
                $post->images()->delete();
                
                $resizedImages = $this->resizeImage($image, $fileName, $folder);
               
                $imagesPath = array_merge([
                    'original' => $path,
                ], $resizedImages);

                $post->images()->create($imagesPath);
            }
        }

        return redirect('/posts')->with('message', 'Post update successfully.'); 
    }

    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        if ($post->user_id != Auth::user()->id){
            return redirect('/posts')->with('message', 'You can not edith this post.');
        }

        $post->categories()->detach($post->category_ids);
        $post->tags()->detach($post->category_ids);
        $post->delete();
        return redirect('/posts')->with('message', 'Post deleted successfully.');
    }
}