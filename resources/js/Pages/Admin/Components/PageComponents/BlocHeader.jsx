export default function BlocHeader({number,title,label,icone})
{
    return   <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-warning h-100">
        <div class="card-body">
            <div class="d-flex align-items-center mb-2">
                <div class="avatar me-4">
                    <span className={`avatar-initial rounded-3 ${label}`}>
                        <i class= {icone}  ></i>
                    </span>
                </div>
                <h4 class="mb-0">{number}</h4>
            </div>
            <h6 class="mb-0 fw-normal">{title}</h6>
        </div>
    </div>
</div>
}