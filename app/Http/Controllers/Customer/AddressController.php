<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index()
    {
        $addresses = $this->customer()->addresses()->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Addresses retrieved successfully',
            'data' => $addresses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'is_default' => 'sometimes|boolean',
        ]);

        $address = DB::transaction(function () use ($validated): Address {
            $user = $this->customer();
            $makeDefault = (bool) ($validated['is_default'] ?? false);

            if ($makeDefault || !$user->addresses()->exists()) {
                $user->addresses()->update(['is_default' => false]);
                $validated['is_default'] = true;
            }

            return $user->addresses()->create($validated);
        });

        return response()->json([
            'success' => true,
            'message' => 'Address created successfully',
            'data' => $address,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:255',
            'recipient_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'address' => 'sometimes|required|string',
            'is_default' => 'sometimes|boolean',
        ]);

        $user = $this->customer();
        $address = $user->addresses()->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found',
            ], 404);
        }

        DB::transaction(function () use ($address, $validated, $user): void {
            if (($validated['is_default'] ?? false) === true) {
            $user->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($validated);
        });

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data' => $address->fresh(),
        ]);
    }

    public function destroy(int $id)
    {
        $user = $this->customer();
        $address = $user->addresses()->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found',
            ], 404);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $user->addresses()->latest('id')->first()?->update(['is_default' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully',
        ]);
    }

    public function setDefault(int $id)
    {
        $user = $this->customer();
        $address = $user->addresses()->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found',
            ], 404);
        }

        DB::transaction(function () use ($address, $user): void {
            $user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Default address updated successfully',
            'data' => $address->fresh(),
        ]);
    }

    private function customer(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
