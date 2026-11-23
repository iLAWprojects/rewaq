<?php

namespace App\Http\Controllers;

use App\Services\BookLookup\IsbnLookupService;
use Illuminate\Http\JsonResponse;

class BookLookupController extends Controller
{
    /**
     * جلب بيانات كتاب بالـ ISBN: الفهرس الداخلي أولًا ثم Open Library وGoogle Books وWorldCat وAmazon.
     */
    public function isbn(string $isbn, IsbnLookupService $lookup): JsonResponse
    {
        $result = $lookup->lookup($isbn);

        return response()->json($result, $result['status'] === 'invalid' ? 422 : 200);
    }
}
