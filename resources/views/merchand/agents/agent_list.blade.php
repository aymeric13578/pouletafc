@extends('admin.components.layout')

@section('main')
<!-- Edit agent modal -->
<div class="modal fade" id="editAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editAgentForm">
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
                            <label for="shop_id" class="form-label">Caution <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="caution" name="caution" placeholder="Saisissez le numéro de la caution" />
                        </div>
                        {{--  <div class="col-lg-6">
                            <label class="form-label" for="location_plan_file">Plan de localisation <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="location_plan_file" name="location_plan_file" />
                        </div>  --}}
                      </div>
                      {{--  <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="identity_card_file">Pièce didentité</label>
                            <input type="file" class="form-control" id="identity_card_file" name="identity_card_file" />
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="photo">Photo</label>
                            <input type="text" class="form-control" id="photo" name="photo" />
                        </div>
                      </div>  --}}
                      <div class="row mb-3">

                        {{--  <div class="col-lg-6">
                            <select class="form-select" id="shop_id" name="shop_id">
                                <option selected>Sélectionnez une caution</option>
                                @foreach ($cautions as $caution)
                                    <option value="{{ $caution->{'id'} }}">{{ $caution->{'amount'} }}</option>
                                @endforeach
                            </select>
                        </div>  --}}
                      </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary"
                    data-bs-dismiss="modal">Fermer</button>
                <button id="editAgentSaveButton" type="button"
                    class="btn btn-primary">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">eCommerce /</span> Liste des agent
</h4>



<!-- Product List Table -->
@if (session()->has('message'))
        <div class="alert alert-success">
            {{ session()->get('message') }}
        </div>
    @endif
<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Liste</h5>
    <div class="d-flex justify-content-between align-items-center row py-3 gap-3 gap-md-0">
      <div class="col-md-4 product_status"></div>
      <div class="col-md-4 product_category"></div>
      <div class="col-md-4 product_stock"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive text-nowrap">
    <table id="agent-table" class="datatables table">
      <thead class="border-top">
        <tr>
          <th>Référence</th>
          <th>Nom complet agent</th>
          <th>Numéro de téléphone</th>
      
          <th>Caution</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($agents as $item)
        <tr>
           <td>{{ $item->{'registration_number'} }}</td>
           <td>{{ $item->{'agent_name'} }}</td>
           <td>{{ $item->{'phone'} }}</td>
          
           <td>{{ $item->{'caution'} }}</td>
        



             
            <td>
                  @if ($item->status == "Success")
                     <span class="badge bg-success">Actif</span>
                 
                  @elseif ($item->status == "pending")
                     <span class="badge bg-info">En attente</span>
                  @else
                    <span class="badge bg-danger">Suspendu</span>
                  @endif
               </td>
               <td>
               <a class="edit-agent" about="{{ $item->{'id'} }}" href="javascript:void(0);" title="Modifier agent"><i class="ti ti-pencil me-1 text-primary"></i></a>

               @if($item->status == "pending" || $item->status == null)
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'agents' , 'status'=>'Success' ])}}"    class='badge bg-success' >Activer</a>
               @endif
               @if($item->status == "Success")
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'agents' , 'status'=>'Pending' ])}}"    class='badge bg-danger' >Desactiver</a>
               @endif


              
              
              </td>             





        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
</div>
          <!-- / Content -->

@endsection
@section('page-script')
<script type="text/javascript">

    let agentEditionId;
    $(document).ready(function (){

        //Click on .edit-agent
      $("body").on("click", ".edit-agent", function (event) {
        event.preventDefault();

        let id = $(this).attr("about");
        //let name = $(this).parent().parent().children().first().html();
        let name = $(this).parent().parent().children(":eq(1)").html();
        let phone = $(this).parent().parent().children(":eq(2)").html();
        let identityCardNumber = $(this).parent().parent().children(":eq(3)").html();
        let caution = $(this).parent().parent().children(":eq(7)").html();
        console.log(caution);
        $("#agent_name").val(name);
        $("#phone").val(phone);
        $("#national_identity_card_number").val(identityCardNumber);
        $("#caution").val(caution);

        editAgentModal = new bootstrap.Modal(document.getElementById("editAgentModal"), {
          "backdrop": "static",
          "keyboard": false,
        });
        editAgentModal.show();
        agentEditionId = id;
      });

      //Click on editAgentSaveButton
      $("#editAgentSaveButton").on("click", function (event) {
        event.preventDefault();
        let $this = $(this);
        let parameters = {
          id: agentEditionId
        };
        let name = $("#agent_name").val();
        if (name === "") {
          toastr.warning("Veuillez renseigner le champ nom de lagent");
          return;
        }
        parameters["agent_name"] = name;

        let phone = $("#phone").val();
        parameters["phone"] = phone;

        let identityCardNumber = $("#national_identity_card_number").val();
        parameters["national_identity_card_number"] = identityCardNumber;

        let caution = $("#caution").val();
        parameters["caution"] = caution;

        /*let caution = $("#caution_id").val();
        if (caution === "") {
            toastr.warning("Veuillez renseigner le champ caution");
            return;
          }
        parameters["caution_id"] = caution;*/

        parameters["_token"] = "{{ csrf_token() }}",
        //Loader.setLoading($this);
        $.ajax({
          url: "{{ route('agentedition') }}",
          type: "post",
          dataType: "json",
          data: parameters,
          success: function (data) {

            if (data.code === 100) {
                editAgentModal.hide();
              setTimeout(function(){
                //datatableInstance.ajax.reload();
                window.parent.location.reload();
              }, 2000);
              toastr.success("Agent modifiée avec succès");
            } else {
              //Error occurred
              toastr.error("Une erreur est survenue durant l'opération!");
            }
            //Loader.removeLoading($this);
          },
          error: function () {
            toastr.error("Une erreur est survenue durant l'opération!");
            //Loader.removeLoading($this);
          }
        });
      });

      //Click on .delete-cat
      $("body").on("click", ".delete-agent", async function (event) {
        event.preventDefault();
        let name = $(this).parent().parent().children(":eq(1)").html();
        let id = $(this).attr("about");

        let $this = $(this);
        if (confirm(`Voulez-vous réellement supprimer l'agent ${name} ?`)) {
          //Loader.setLoading($this);
          $.ajax({
            url: "{{ route('agentdeletion') }}",
            data: {
              id: id,
              _token: "{{ csrf_token() }}"
            },
            type: "post",
            dataType: "json",
            success: function (data) {
              if (data.code === 100) {
                toastr.success("Agent supprimée avec succès!");
                setTimeout(function(){
                    //datatableInstance.ajax.reload();
                    window.parent.location.reload();
                  }, 2000);
              } else {
                toastr.error("Une erreur est survenue durant l'opération!");
              }
              //Loader.removeLoading($this);
            },
            error: function () {
              toastr.error("Une erreur est survenue durant l'opération!");
              //Loader.removeLoading($this);
            },
          });
        }
      });

      datatableInstance = $("#agent-table").DataTable({
        "processing": true,
        "language": {
          "search": '',
          "searchPlaceholder": "Rechercher..."
        },
      });
    });
</script>
@endsection

