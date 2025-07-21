@extends('admin.components.layout')

@section('main')

<h4 class="py-3 mb-0">
  <span class="text-muted fw-light">eCommerce /</span><span class="fw-medium"> Ajouter agent</span>
</h4>

@if (session()->has('message'))
<div class="alert alert-success">
    {{ session()->get('message') }}
</div>
@endif

@if (session()->has('danger'))
<div class="alert alert-danger">
    {{ session()->get('danger') }}
</div>
@endif

<div class="app-ecommerce">
  <div class="row">

    <!-- First column-->
    <div class="col-12 col-lg-12">
      <!-- Product Information -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-tile mb-0">Informations sur lagent</h5>
        </div>
        <div class="card-body">
            <form action="{{route('storeagent')}}" method="POST" enctype="multipart/form-data">
                @csrf
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="agent_name">Nom complet agent <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="agent_name" name="agent_name" placeholder="Saisissez le nom complet de l'agent" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="phone">Numéro téléphone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="phone" name="phone" placeholder="Saisissez le numéro de téléphone de l'agent" />
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="national_identity_card_number">Numéro pièce didentité</label>
                    <input type="text" class="form-control" id="national_identity_card_number" name="national_identity_card_number" placeholder="Saisissez le numéro de la pièce d'identité" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="location_plan_file">Plan de localisation <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="location_plan_file" name="location_plan_file" />
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="identity_card_file">Pièce didentité</label>
                    <input type="file" class="form-control" id="identity_card_file" name="identity_card_file" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="photo">Photo</label>
                    <input type="file" class="form-control" id="photo" name="photo" />
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label for="shop_id" class="form-label">Caution <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="caution" name="caution" placeholder="Saisissez le numéro de la caution" />
                </div>
                {{--  <div class="col-lg-6">
                    <select class="form-select" id="shop_id" name="shop_id">
                        <option selected>Sélectionnez une caution</option>
                        @foreach ($cautions as $caution)
                            <option value="{{ $caution->{'id'} }}">{{ $caution->{'amount'} }}</option>
                        @endforeach
                    </select>
                </div>  --}}
              </div>

              <div class="modal-footer">
                <div class="">
                  <button type="submit" class="btn btn-primary">Ajouter agent</button>
                </div>
              </div>
            </form>

        </div>

    </div>
</div>
<!-- / Content -->

@endsection

