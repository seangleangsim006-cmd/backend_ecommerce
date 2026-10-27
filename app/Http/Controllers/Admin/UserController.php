<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //
    public function index(){
        $users = User::all();
        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users
        ], 200);
    }

    public function show($id){
        $user = User::find($id);
        if(!$user){
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }
        return response()->json([
            'message' => 'User retrieved successfully',
            'data' => $user
        ], 200);
    }

    public function destroy($id){
        $user = User::find($id);
        if(!$user){
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }
        $user->delete();
        return response()->json([
            'message' => 'User deleted successfully'
        ], 200);
    }

    public function updateRole(Request $request, $id){
        $user = User::find($id);
        if(!$user){
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }
        $validate = Validator($request->all(),[
           'role' => 'required|in:customer,merchant,admin'
        ]);
        if($validate->fails()){
            return response()->json([
                'status' => false,
                'message' => "Invalid input",
                'error' => $validate->errors()
            ],422);
        }
        $previousRole = $user->role;
        $user->role = $request->role;

        // A newly promoted merchant must be reviewed by an administrator.
        if ($previousRole !== 'merchant' && $request->role === 'merchant') {
            $user->merchant_status = 'pending';
            $user->merchant_status_reason = null;
            $user->merchant_status_changed_at = now();
        }

        $user->save();
        return response()->json([
            'message' => 'User role updated successfully',
            'data' => $user
        ], 200);
    }

    public function update(Request $request, $id){
        $user = User::find($id);
        if(!$user){
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }
        $validate = Validator($request->all(),[
            'name' => 'required|string|max:255',
            'email' => 'required|email|string|max:255|unique:users,email,'.$user->id,
        ]);
        if($validate->fails()){
            return response()->json([
                'status' => false,
                'message' => "Invalid input",
                'error' => $validate->errors()
            ],422);
        }
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();
        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user
        ], 200);
    }

    

}
