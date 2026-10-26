<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Products\CategoryTree;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(CategoryTree $categoryTree): Response
    {
        return Inertia::render('catalog/categories', ['categories' => $categoryTree->handle()]);
    }
}
