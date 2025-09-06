<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'service' => 'General Inquiry',
            'message' => 'This is a test message.'
        ]);
    }
}
