<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAccessory;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * 商品一覧を表示
     */
    public function index()
    {
        $products = Product::with('category')->orderByDesc('id')->paginate(20); // 20件ごとにページング
        return view('admin.products.index', compact('products'));
    }

    /**
     * 新しい商品登録フォームを表示
     */
    public function create()
    {
        $categories = Category::all();
        $products = Product::all();
        return view('admin.products.create', compact('categories', 'products'));
    }

    /**
     * 新しい商品を保存
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_code' => 'required|string|max:255',
            'product_name' => 'required|string|max:255',
            'set_sale_name' => 'nullable|string|max:255',
            'category_id' => 'required',
            'product_image' => 'nullable|image',
            'description' => 'nullable|string',
            'volume' => 'nullable|string',
            'price' => 'required|integer',
            'wholesale_price' => 'nullable|integer',
            'retail_price' => 'nullable|integer',
            'salon_price' => 'nullable|integer',
            'salon_product_code' => 'nullable|string',
            'tax_rate' => 'required|integer',
            'jan' => 'nullable|string',
            'lot' => 'nullable|string',
            'unit_quantity' => 'required|integer|min:1',
            'status' => 'required|in:available,unavailable',
            'agent_sale_flag' => 'nullable|integer',
            'single_sale_prohibited' => 'nullable|boolean',
            'accessories' => 'nullable|array',
            'accessories.*.product_id' => 'nullable|exists:products,id',
            'accessories.*.quantity' => 'nullable|integer|min:1|max:9999',
        ]);

        // 商品画像を保存
        if ($request->hasFile('product_image')) {
            $data['product_image'] = $request->file('product_image')->store('images', 'public');
        }

        // チェックボックスのデフォルト
        $data['agent_sale_flag'] = $request->has('agent_sale_flag') ? 1 : 0;
        $data['single_sale_prohibited'] = $request->has('single_sale_prohibited') ? 1 : 0;

        $product = Product::create($data);
        
        // 付属商品の保存
        if ($request->has('accessories')) {
            foreach ($request->accessories as $accessory) {
                if (!empty($accessory['product_id']) && !empty($accessory['quantity'])) {
                    ProductAccessory::create([
                        'main_product_id' => $product->id,
                        'accessory_product_id' => $accessory['product_id'],
                        'quantity' => $accessory['quantity']
                    ]);
                }
            }
        }
        
        return redirect()->route('admin.products.index')->with('success', 'Product created successfully');
    }

    /**
     * 商品詳細を表示
     */
    public function show(Product $product)
    {
        $product->load('category'); // カテゴリ情報をロード
        return view('admin.products.show', compact('product'));
    }

    /**
     * 商品編集フォームを表示
     */
    public function edit(Product $product)
    {
        $categories = Category::all();
        $products = Product::all();
        $product->load('category', 'accessories.accessoryProduct');
        return view('admin.products.edit', compact('product', 'categories', 'products'));
    }

    /**
     * 商品情報を更新
     */
    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'product_code' => 'required|string|max:255',
            'product_name' => 'required|string|max:255',
            'set_sale_name' => 'nullable|string|max:255',
            'category_id' => 'required',
            'product_image' => 'nullable|image',
            'description' => 'nullable|string',
            'volume' => 'nullable|string',
            'price' => 'required|integer',
            'wholesale_price' => 'nullable|integer',
            'retail_price' => 'nullable|integer',
            'salon_price' => 'nullable|integer',
            'salon_product_code' => 'nullable|string',
            'tax_rate' => 'required|integer',
            'jan' => 'nullable|string',
            'lot' => 'nullable|string',
            'unit_quantity' => 'required|integer|min:1',
            'status' => 'required|in:available,unavailable',
            'agent_sale_flag' => 'nullable|integer',
            'single_sale_prohibited' => 'nullable|boolean',
            'accessories' => 'nullable|array',
            'accessories.*.product_id' => 'nullable|exists:products,id',
            'accessories.*.quantity' => 'nullable|integer|min:1|max:9999',
        ]);

        // チェックがオフのときにデフォルト値をセット
        $data['agent_sale_flag'] = $request->has('agent_sale_flag') ? 1 : 0;
        $data['single_sale_prohibited'] = $request->has('single_sale_prohibited') ? 1 : 0;

        // 既存の画像を削除して新しい画像を保存
        if ($request->hasFile('product_image')) {
            if ($product->product_image) {
                Storage::disk('public')->delete($product->product_image);
            }
            $data['product_image'] = $request->file('product_image')->store('images', 'public');
        }

        $product->update($data);
        
        // 既存の付属商品を削除
        $product->accessories()->delete();
        
        // 新しい付属商品の保存
        if ($request->has('accessories')) {
            foreach ($request->accessories as $accessory) {
                if (!empty($accessory['product_id']) && !empty($accessory['quantity'])) {
                    ProductAccessory::create([
                        'main_product_id' => $product->id,
                        'accessory_product_id' => $accessory['product_id'],
                        'quantity' => $accessory['quantity']
                    ]);
                }
            }
        }
        
        return redirect()->route('admin.products.index')->with('success', 'Product updated successfully');
    }

    /**
     * 商品を削除
     */
    public function destroy(Product $product)
    {
        // 画像ファイルを削除
        if ($product->product_image) {
            Storage::disk('public')->delete($product->product_image);
        }
        
        // 付属商品を削除（外部キー制約でカスケード削除されるが明示的に削除）
        $product->accessories()->delete();

        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Product deleted successfully');
    }

    public function toggleStatus(Product $product)
    {
        $product->status = $product->status === 'available' ? 'unavailable' : 'available';
        $product->save();

        return response()->json(['status' => $product->status]);
    }


    /**
     * 商品のステータスを更新（販売中 ⇔ 完売）する非同期用エンドポイント
     *
     * @param  \Illuminate\Http\Request  $request  リクエスト（product_id と status を受け取る）
     * @return \Illuminate\Http\JsonResponse       JSON形式のレスポンスを返す
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $product = Product::findOrFail($request->id);

        $validated = $request->validate([
            'status' => 'required|in:available,unavailable',
        ]);

        $product->status = $validated['status'];
        $product->save();

        return response()->json(['message' => 'ステータスを更新しました', 'status' => $product->status]);
    }
}
