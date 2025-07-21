@extends('merchand.components.layout')

@section('main')

<h4 class="py-3 mb-0">
  <span class="text-muted fw-light">eCommerce /</span><span class="fw-medium"> Ajouter boutique</span>
</h4>

<div class="app-ecommerce">
  <div class="row">

    <!-- First column-->
    <div class="col-12 col-lg-12">
      <!-- Product Information -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-tile mb-0">Informations sur la boutique</h5>
        </div>
        <div class="card-body">
            <form action="{{route('storeshop')}}" method="POST" enctype="multipart/form-data">
                @csrf
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="category_name">Nom boutique</label>
                    <input type="text" required class="form-control" id="shop_name" name="shop_name" placeholder="Saisissez le nom de la boutique" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="shop_code">Code boutique</label>
                    <input type="text"  required class="form-control" id="shop_code" name="shop_code" placeholder="Saisissez le code de la boutique" />
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-lg-6">
                <label class="form-label" for="shop_code">Type de boutique</label>
                   
                <select class="form-select" id="type" name="type" aria-label="Catégorie" required>
              <option value="AFC">AFC</option>
              <option value="INDEPENDANT">INDEPENDANT</option>  
               </select>
              
              
              
              
              
              
              </div>
                <div class="col-lg-6">
                    <label class="form-label" for="shop_telephone1">Numéro téléphone</label>
                    <input type="text"  required class="form-control" id="shop_telephone1" name="shop_telephone1" placeholder="Saisissez le numéro de téléphone de la boutique" />
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-lg-6">
                    <label class="form-label" for="shop_address">Adresse boutique</label>
                    <input type="text"  required class="form-control" id="shop_address" name="shop_address" placeholder="Saisissez l'adresse de la boutique" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="shop_email1">E-mail boutique</label>
                    <input type="text"  required class="form-control" id="shop_email1" name="shop_email1" placeholder="Saisissez l'email de la boutique" />
                </div>
              </div>
              <div class="row mb-3">
              
              <div class="col-lg-6">
                    <label class="form-label" for="shop_logo">Logo de la boutique</label>
                    <input type="file" required class="form-control" id="shop_logo" name="shop_logo" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="shop_logo">Banniere</label>
                    <input type="file"  required class="form-control" id="shop_logo" name="banner" />
                </div>

              </div>
              <div class="row mb-3">
              
                <div class="col-lg-6">
                    <label class="form-label" for="shop_commercial_register">Numéro registre de commerce</label>
                    <input type="text"  required class="form-control" id="shop_commercial_register" name="shop_commercial_register" placeholder="Saisissez le numéro de registre de commerce" />
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="shop_commercial_register_file">Document du registre de commerce</label>
                    <input type="file"  required class="form-control" id="shop_commercial_register_file" name="shop_commercial_register_file" />
                </div>
              </div>
              <div class="row mb-3">
                    <div class="col lg-6">
                        <label class="form-label" for="description">Description <span
                            class="text-danger"></span></label></label>
                        <textarea  required class="form-control" name="description" id="description" cols="10" rows="2"></textarea>
                    </div>
                </div>

          
              <div class="modal-footer">
                <div class="">
                  <button type="submit" class="btn btn-primary">Ajouter boutique</button>
                </div>
              </div>
            </form>

        </div>

    </div>
</div>
<!-- / Content -->

@endsection

