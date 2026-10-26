<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    // Find people to invite to a project, by name.
    public function __invoke(Request $request)
    {
        $q = $request->validate(['q' => 'required|string|min:2|max:50'])['q'];

        return User::where('name', 'like', '%'.$q.'%')->limit(10)->get();
    }
}
