@extends('admin.components.layout')

@section('main')
    <h4 class="py-3 mb-0">
        <span class="text-muted fw-light">eCommerce /</span><span class="fw-medium"> Parameter</span>
    </h4>

    <div class="app-ecommerce">
        <div class="row">
            <!-- Notifications -->
            @if (session('success'))
                <div class="alert alert-success alert-dismissible" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- First column-->
            <div class="col-12 col-lg-12">
                <!-- Parameters Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Liste des Paramètres</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Prix/Kilometrage Clando</th>
                                        <th>Prix/Kilometrage Commande</th>
                                        <th>Prix Minimal Clando</th>
                                        <th>Prix Minimal Commande</th>
                                        <th>pourcentage Vip</th>
                                        <th>Commission Agent Clando</th>
                                        <th>Commission Agent Commande</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($parameters as $parameter)
                                        <tr>
                                            <td>{{ $parameter->clando_kilometer }}</td>
                                            <td>{{ $parameter->command_kilometer }}</td>
                                            <td>{{ $parameter->min_price_clando }}</td>
                                            <td>{{ $parameter->min_price_command }}</td>
                                             <td>{{ $parameter-> vip_percentage }}%</td>
                                            
                                           
                                            <td>{{ $parameter->clando_agent_commission }}%</td>
                                            <td>{{ $parameter->clando_agent_command }}%</td>
                                            <td>{{ $parameter->status }}</td>
                                            <td>
                                                @if($parameter->status == 'Success')
                                                    <form action="{{ route('parameters.destroy', $parameter->id) }}" method="POST" style="display: inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Voulez-vous vraiment supprimer?')">Supprimer</button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('parameters.validate', $parameter->id) }}" method="POST" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm">Valider</button>
                                                    </form>
                                                    <form action="{{ route('parameters.destroy', $parameter->id) }}" method="POST" style="display: inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Voulez-vous vraiment supprimer?')">Supprimer</button>
                                                    </form>
                                                @endif  
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Product Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-tile mb-0">Entrez paramètres</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('parameters.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label" for="clando_kilometer">Prix/Kilometrage clando</label>
                                <div class="col-sm-10">
                                    <input type="number" class="form-control" id="clando_kilometer" name="clando_kilometer" placeholder="" value="{{ old('clando_kilometer') }}" />
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label" for="command_kilometer">Prix/Kilometrage commande</label>
                                <div class="col-sm-10">
                                    <input type="number" class="form-control" id="command_kilometer" name="command_kilometer" placeholder="" value="{{ old('command_kilometer') }}" />
                                </div>
                            </div>
                              <div class="row mb-3">
                                <label class="col-sm-2 col-form-label" for="command_kilometer">Pourcentage VIP clando(en %)</label>
                                <div class="col-sm-10">
                                    <input type="number" class="form-control" id="command_kilometer" name="vip_percentage" placeholder="" value="{{ old('vip_percentage') }}" />
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label" for="min_price_clando">Prix minimal clando</label>
                                <div class="col-sm-10">
                                    <input type="number" class="form-control" id="min_price_clando" name="min_price_clando" placeholder="" value="{{ old('min_price_clando') }}" />
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label" for="min_price_command">Prix minimal commande</label>
                                <div class="col-sm-10">
                                    <input type="number" class="form-control" id="min_price_command" name="min_price_command" placeholder="" value="{{ old('min_price_command') }}" />
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label" for="clando_agent_commission">Commission agent clando (en %)</label>
                                <div class="col-sm-10">
                                    <input type="number" class="form-control" id="clando_agent_commission" name="clando_agent_commission" placeholder="" value="{{ old('clando_agent_commission') }}" />
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label" for="clando_agent_command">Commission agent commande (en %)</label>
                                <div class="col-sm-10">
                                    <input type="number" class="form-control" id="clando_agent_command" name="clando_agent_command" placeholder="" value="{{ old('clando_agent_command') }}" />
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="">
                                    <button type="submit" class="btn btn-primary">Valider</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- / Content -->
        </div>
    </div>
@endsection