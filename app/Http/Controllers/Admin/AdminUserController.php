<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function farmers()
    {
        $farmers = User::where('role', 'farmer')
                       ->orderBy('full_name')
                       ->get();
        return view('admin.users.farmers', compact('farmers'));
    }

    public function technicians()
    {
        $technicians = User::where('role', 'technician')
                           ->orderBy('full_name')
                           ->get();
        return view('admin.users.technicians', compact('technicians'));
    }

    public function createTechnician()
    {
        return view('admin.users.create-technician');
    }

    public function storeTechnician(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|min:8',
        ]);

        User::create([
            'full_name' => $request->full_name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => 'technician',
            'status'    => 'approved',
        ]);

        return redirect()->route('admin.technicians')
                         ->with('success', 'Technician account created successfully!');
    }

    public function update(Request $request, $id)
    {
        $user = User::whereIn('role', ['farmer', 'technician'])->findOrFail($id);

        $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,'.$id,
            'status'    => 'required|in:pending,approved,declined',
        ]);

        $user->update($request->only(['full_name', 'email', 'status']));

        $route = $user->role === 'farmer' ? 'admin.farmers' : 'admin.technicians';
        return redirect()->route($route)->with('success', 'User updated successfully.');
    }

    public function approve($id)
    {
        $user = User::whereIn('role', ['farmer', 'technician'])->findOrFail($id);
        $user->update(['status' => 'approved']);

        $route = $user->role === 'farmer' ? 'admin.farmers' : 'admin.technicians';
        return redirect()->route($route)->with('success', ucfirst($user->role) . ' approved successfully!');
    }

    public function decline($id)
    {
        $user = User::whereIn('role', ['farmer', 'technician'])->findOrFail($id);
        $user->update(['status' => 'declined']);

        $route = $user->role === 'farmer' ? 'admin.farmers' : 'admin.technicians';
        return redirect()->route($route)->with('success', ucfirst($user->role) . ' declined.');
    }

    public function delete($id)
    {
        $user = User::whereIn('role', ['farmer', 'technician'])->findOrFail($id);
        $role = $user->role;
        $user->delete();

        $route = $role === 'farmer' ? 'admin.farmers' : 'admin.technicians';
        return redirect()->route($route)->with('success', ucfirst($role) . ' deleted successfully.');
    }
}