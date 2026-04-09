<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/books',
        tags: ['Books'],
        summary: 'Search/list books',
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        $query = Book::query()->orderBy('title');

        $q = request()->string('q')->trim()->toString();
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('author', 'like', "%{$q}%")
                    ->orWhere('isbn', 'like', "%{$q}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/admin/books',
        tags: ['Admin: Books'],
        summary: 'Create a book',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['title','author','isbn','total_copies'])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'max:50', 'unique:books,isbn'],
            'description' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'total_copies' => ['required', 'integer', 'min:0'],
            'available_copies' => ['nullable', 'integer', 'min:0'],
        ]);

        if (!array_key_exists('available_copies', $data) || $data['available_copies'] === null) {
            $data['available_copies'] = $data['total_copies'];
        }

        if ($data['available_copies'] > $data['total_copies']) {
            return response()->json(['message' => 'available_copies cannot exceed total_copies.'], 422);
        }

        $book = Book::create($data);

        return response()->json($book, 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/books/{id}',
        tags: ['Books'],
        summary: 'Get a book',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id)
    {
        return response()->json(Book::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/admin/books/{id}',
        tags: ['Admin: Books'],
        summary: 'Update a book',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent()),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $book = Book::findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'author' => ['sometimes', 'string', 'max:255'],
            'isbn' => ['sometimes', 'string', 'max:50', 'unique:books,isbn,' . $book->id],
            'description' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'total_copies' => ['sometimes', 'integer', 'min:0'],
            'available_copies' => ['sometimes', 'integer', 'min:0'],
        ]);

        $book->fill($data);

        if ($book->available_copies > $book->total_copies) {
            return response()->json(['message' => 'available_copies cannot exceed total_copies.'], 422);
        }

        $book->save();

        return response()->json($book);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/admin/books/{id}',
        tags: ['Admin: Books'],
        summary: 'Delete a book',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function destroy(string $id)
    {
        $book = Book::findOrFail($id);
        $book->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
