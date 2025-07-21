<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ParametersController extends Controller
{
    public function index()
    {
        $parameters = Parameter::orderBy('id', 'desc')->get();
        return view('admin.parameters.parameters', compact('parameters'));
    }

    public function storeParameters(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clando_kilometer' => 'required|numeric',
            'command_kilometer' => 'required|numeric',
            'min_price_clando' => 'required|numeric',
            'min_price_command' => 'required|numeric',
            'clando_agent_commission' => 'required|numeric',
            'clando_agent_command' => 'required|numeric',
            'vip_percentage' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Create new parameter
        Parameter::create([
            'clando_kilometer' => $request->clando_kilometer,
            'command_kilometer' => $request->command_kilometer,
            'min_price_clando' => $request->min_price_clando,
            'min_price_command' => $request->min_price_command,
            'clando_agent_commission' => $request->clando_agent_commission,
            'clando_agent_command' => $request->clando_agent_command,
            'vip_percentage' => $request->vip_percentage,
            'status' => 'Pending'
        ]);

        return redirect()->route('parameters.index')->with('success', 'Paramètres ajoutés avec succès.');
    }

    public function validateParameter($id)
    {
        $parameter = Parameter::findOrFail($id);

        // Set all other parameters to Pending
        Parameter::where('id', '!=', $id)->update(['status' => 'Pending']);

        // Validate the selected parameter
        $parameter->update(['status' => 'Success']);

        return redirect()->route('parameters.index')->with('success', 'Paramètre validé avec succès.');
    }

    public function destroy($id)
    {
        $parameter = Parameter::findOrFail($id);
        $parameter->delete();

        return redirect()->route('parameters.index')->with('success', 'Paramètre supprimé avec succès.');
    }
}