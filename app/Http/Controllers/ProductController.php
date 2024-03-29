<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use App\Models\ProductDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductController extends BaseController
{   

    public function upload(Request $request){
        //A product can be uploaded

        //validate the input
        $data = $request->validate([
            'category'=>['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'string'],
            'description'=>['required', 'string'],
            'manufacturer'=>['required','string'],
            'nafdac_no'=>['nullable', 'string'],
            'expiry'=>['nullable', 'date'],
            'image1'=>['required', 'image', 'mimes:jpeg,png,jpg,gif','max:2048'],
            'image2'=>['nullable', 'image', 'mimes:jpeg,png,jpg,gif','max:2048'],
            'image3'=>['nullable', 'image', 'mimes:jpeg,png,jpg,gif','max:2048'],
        ]);

        //CREATE CATEGORY: 
        
        //if the category exists, get Id. Else, insert:
        $catExists = Category::where('name',$data['category'])->first();
        $catId = "";
        if ($catExists !== null)
        {
            $catId = $catExists->id;
            $category = Category::find($catId);
            $category->in_stock_count += 1;
            $category->sold_out_count = 0;
            $category->save();
        }
        else
        {   
            $category = new Category;
            $category->name = $data['category'];
            $category->in_stock_count += 1;
            $category->sold_out_count = 0;
            $category->save();
            $catId = $category->id;
        }
        
        //CREATE THE PRODUCT:
        $product = Product::create([
            'name' => $data['name'],
            'price'=> $data['price'],
            'availability'=>'IN_STOCK',
            'category_id' => $catId,
        ]);
        //CREATE OTHER PRODUCT DETAILS:
        $product
        ->detail()
        ->create([
            'description' => $data['description'],
            'manufacturer' => $data['manufacturer'],
            'product_id' =>$product->id,
            'expiry_date'=> $data['expiry'],
            'nafdac_reg_no'=> $data['nafdac_no']
        ]);
        
        //UPLOAD IMAGES
        $image = new ProductImage;
        $fileFieldNames = ['image1', 'image2', 'image3'];
        
        foreach ($fileFieldNames as $field){
            if ($request->hasFile($field))
            {
                $this->uploadImage($request, $field, $image, $product);
                $image->save();
            }
           
        };

        $info  = [
            'product' => $product,
            'category' => $product->category,
            'description'=> $product->detail,
            'images'=> $image
        ];
        return $this->sendResponse($info, "Product Uploaded.");
    }

    private function uploadImage(Request $request,$imgFieldName, $image, $product){
        $pic = $request->file($imgFieldName);
        $name = "Prod ".$product->id." ".$imgFieldName.".".$pic->guessExtension();
        $destinationPath = public_path($imgFieldName);
        $image->$imgFieldName = $name;
        $image->product_id = $product->id;
        $pic->move($destinationPath, $name);
    }

    //Delete a product
    public function deleteProduct($id){
        Product::where('id', $id)->delete();
        return $this->sendResponse("Product deleted", "Product deleted");
    }

    //Fetch specific product
    public function fetchProduct($id){
        $product = Product::find($id);
        if($product==null){
            return $this->sendError('product does not exist', 'product does not exist', 404);
        }
        
        $info = [
            'product'=> $product,
            'detail'=> $product->detail,
            'images'=> $product->images
        ];
        return $this->sendResponse($info, "Product found");
    }

 

    //get all products
    public function get($catId){
        //find if category query param is passed
        $category = Category::find($catId);
        $products = []; 
        //if passed use it to filter products
        if($category != null){
            $products = [
                'products'=> $category->products,
                'detail'=>$category->productDetail,
                'images'=>$category->productImages
            ];
        }
        return $this->sendResponse($products, "All products");
    } 

    //edit product
    public function edit(Request $request, $id){
        $data = $request->validate([
            'category'=>['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'string'],
            'description'=>['required', 'string'],
            'manufacturer'=>['required','string'],
            'nafdac_no'=>['nullable', 'string'],
            'expiry'=>['nullable', 'date'],
            'image1'=>['required', 'image', 'mimes:jpeg,png,jpg,gif','max:2048'],
            'image2'=>['nullable', 'image', 'mimes:jpeg,png,jpg,gif','max:2048'],
            'image3'=>['nullable', 'image', 'mimes:jpeg,png,jpg,gif','max:2048'],
        ]);

        //update category: 
        
        //if the category exists, get Id. Else, insert:
            
            $catExists = Category::where('name',$data['category'])->first();
            $catId = "";
            if ($catExists !== null)
            {
                $catId = $catExists->id;
            }
            else
            {   
                $category = new Category;
                $category->name = $data['category'];
                $category->in_stock_count += 1;
                $category->sold_out_count = 0;
                $category->save();
                $catId = $category->id;
            }

            $product = Product::where('id',$id)->first();
            if($product==null){
                return $this->sendError('product does not exist','product does not exist');
            }
            $product->name = $data['name'];
            $product->price = $data['price'];
            $product->category_id = $catId;

            //update details
            $product
            ->detail()
            ->update([
                'description' => $data['description'],
                'manufacturer' => $data['manufacturer'],
                'product_id' =>$product->id,
                'expiry_date'=> $data['expiry'],
                'nafdac_reg_no'=> $data['nafdac_no']
            ]);

            //upload images
            $image = ProductImage::where('product_id',$id)->first();
            $fileFieldNames = ['image1', 'image2', 'image3'];
            
            foreach ($fileFieldNames as $field){
                if ($request->hasFile($field))
                {
                    $this->uploadImage($request, $field, $image, $product);
                    $image->save();
                }
               
            };
            return $this->sendResponse("Update successful", "Update successful");
    }

    //delete an image
    public function deleteImage($id){
        ProductImage::where('id', $id)->delete();
        return $this->sendResponse("Image deleted", "Image deleted");
    }

    //get featured categories and products

    public function featured(){
        //- get 5 random categories 
        //- Get products of selected images
		//- get the images of the products
       $data = Category::with('products')
       ->with('productImages')
       ->inRandomOrder()
       ->take(5)->get();
        return $this->sendResponse($data, "Featured Categories");
    }
    //get the category of a specific product

    public function productCategory($catId){
        $category = Category::find($catId);
        
        if($category != null){
            return $this->sendResponse($category, "Specific Product Category");
        }
        return $this->sendResponse("Such category does not exist", "Such category does not exist");
    }
}
