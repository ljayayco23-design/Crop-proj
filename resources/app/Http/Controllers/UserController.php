<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserController extends Controller 
{
    public function index() 
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        // Validate the request data
        $validateData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // create a new user
        $users = User::create ([
            'name' => $validateData['name'],
            'email' => $validateData['email'],
            'password' => $validateData['password'],
        ]);

        // Redirect to user index page with a success message
        return redirect()->route('users.index')->with('success', 'User Created Successfully.');
    }

    public function update(Request $request, $id)
    {
        $validateData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'email|unique:users,email',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->name = $validateData['name'];
        $user->email = $validatedata['email'];

        if(!empty($validateData['password'])) {
            $user->password = bcrypt($validateData['password']);
        }

        // $users = User::update ([
        //     'name' => $validateData['name'],
        //     'email' => $validateData['email'],
        //     'password' => $validateData['password'],
        // ]);

        // Redirect to user index page with a success message
        return redirect()->route('users.index')->with('success', 'User Updated Successfully.');
    }

    public function destroy($id){

    $user = User::findOrFail($id);

    $user->delete();

    return redirect()->route('users.index')->with('success', 'User delete Successfully.');

}
}
?>