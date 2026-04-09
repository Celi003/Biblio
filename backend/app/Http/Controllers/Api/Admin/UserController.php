<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/admin/users',
        tags: ['Admin: Users'],
        summary: 'List users',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        return response()->json(User::query()->orderBy('id')->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/admin/users',
        tags: ['Admin: Users'],
        summary: 'Create user',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name','email','password','role'])),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:' . User::ROLE_USER . ',' . User::ROLE_ADMIN],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/admin/users/{id}',
        tags: ['Admin: Users'],
        summary: 'Get user',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(string $id)
    {
        return response()->json(User::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/admin/users/{id}',
        tags: ['Admin: Users'],
        summary: 'Update user',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent()),
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', 'in:' . User::ROLE_USER . ',' . User::ROLE_ADMIN],
        ]);

        if (array_key_exists('password', $data)) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->fill($data);
        $user->save();

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/admin/users/{id}',
        tags: ['Admin: Users'],
        summary: 'Delete user',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
