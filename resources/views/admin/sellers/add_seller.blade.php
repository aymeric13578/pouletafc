@extends('admin.components.layout')

@section('main')

<h4 class="py-3 mb-0">
  <span class="text-muted fw-light">eCommerce /</span><span class="fw-medium"> Ajouter marchand</span>
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
          <h5 class="card-tile mb-0">Informations sur le marchand</h5>
        </div>
        <div class="card-body">
            <form action="{{route('storeseller')}}" method="POST" enctype="multipart/form-data">
                @csrf
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="seller_full_name">Nom complet marchand <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="seller_full_name" name="seller_full_name" placeholder="Saisissez le nom complet du marchand" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="seller_code">Code marchand</label>
                    <input type="text" class="form-control" id="seller_code" name="seller_code" placeholder="Saisissez le code du marchand" />
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="seller_telephone1">Numéro téléphone 1 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="seller_telephone1" name="seller_telephone1" placeholder="Saisissez le numéro de téléphone 1" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="seller_telephone2">Numéro téléphone 2</label>
                    <input type="text" class="form-control" id="seller_telephone2" name="seller_telephone2" placeholder="Saisissez le numéro de téléphone 2" />
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="seller_telephone3">Numéro téléphone 3</label>
                    <input type="text" class="form-control" id="seller_telephone3" name="seller_telephone3" placeholder="Saisissez le numéro de téléphone 3" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="seller_address">Adresse / Localité</label>
                    <input type="text" class="form-control" id="seller_address" name="seller_address" placeholder="Saisissez l'adresse du marchand" />
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="seller_email1">E-mail marchand 1</label>
                    <input type="text" class="form-control" id="seller_email1" name="seller_email1" placeholder="Saisissez l'email 1 du marchand" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="seller_email2">E-mail marchand 2</label>
                    <input type="text" class="form-control" id="seller_email2" name="seller_email2" placeholder="Saisissez l'email 2 du marchand" />
                </div>

              </div>
              <div class="row mb-3">
                <label for="shop_id" class="form-label">Nom boutique <span class="text-danger">*</span></label>
                <div class="col-lg-6">
                    <select class="form-select" id="shop_id" name="shop_id">
                        <option selected>Sélectionnez une boutique</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->{'id'} }}">{{ $shop->{'shop_name'} }}</option>
                        @endforeach
                    </select>
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="seller_niu">NIU Marchand</label>
                    <input type="text" class="form-control" id="seller_niu" name="seller_niu" placeholder="Saisissez le niu du marchand" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="seller_photo">Photo marchand</label>
                    <input type="file" class="form-control" id="seller_photo" name="seller_photo" />
                </div>
              </div>

              <div class="modal-footer">
                <div class="">
                  <button type="submit" class="btn btn-primary">Ajouter Marchand</button>
                </div>
              </div>
            </form>

        </div>

    </div>
</div>
<!-- / Content -->

@endsection

