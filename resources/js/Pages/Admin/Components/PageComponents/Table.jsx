
import { DataTable } from 'primereact/datatable';
import { Column } from 'primereact/column';
import { useEffect, useState } from 'react';
import { FilterMatchMode, FilterOperator } from 'primereact/api';
import "primereact/resources/themes/lara-light-cyan/theme.css";

import { InputText } from 'primereact/inputtext';
import { IconField } from 'primereact/iconfield';
import { InputIcon } from 'primereact/inputicon';
import { Dropdown } from 'primereact/dropdown';
import { InputNumber } from 'primereact/inputnumber';
import { Button } from 'primereact/button';
import { ProgressBar } from 'primereact/progressbar';


export default  function Table({title,elementsHeadTable,children,elements}) 
{

    const [customers, setCustomers] = useState(null);
    const [filters, setFilters] = useState(null);
    const [loading, setLoading] = useState(false);
    const [globalFilterValue, setGlobalFilterValue] = useState('');

    useEffect(() => {
        
        initFilters();
    }, []);

    const initFilters = () => {
        setFilters({
            global: { value: null, matchMode: FilterMatchMode.CONTAINS },
     
        });
        setGlobalFilterValue('');
    }
    
    const renderHeader = () => {
        return (
            <div className="flex justify-content-between">
                <Button type="button" icon="pi pi-filter-slash" label="reset" className='btn btn-primary' outlined onClick={clearFilter} />
                <IconField iconPosition="left">
                    <InputIcon className="pi pi-search" />
                    <InputText value={globalFilterValue} onChange={onGlobalFilterChange} placeholder="rechercher" />
                </IconField>
            </div>
        );
    };
    const onGlobalFilterChange = (e) => {
        const value = e.target.value;
        let _filters = { ...filters };

        _filters['global'].value = value;

        setFilters(_filters);
        setGlobalFilterValue(value);
    };
    const clearFilter = () => {
        initFilters();
    };
    const header = renderHeader();
    return <div class="col-12 order-5">
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title mb-0">
                <h5 class="m-0 me-2">
                  {title}
                </h5>
            </div>
            <div class="dropdown">
                <button
                    class="btn btn-text-secondary rounded-pill text-muted border-0 p-1"
                    type="button"
                    id="routeVehicles"
                    data-bs-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false"
                >
                    <i class="ri-more-2-line ri-20px"></i>
                </button>
                <div
                    class="dropdown-menu dropdown-menu-end"
                    aria-labelledby="routeVehicles"
                >
                    <a
                        class="dropdown-item"
                        href="javascript:void(0);"
                    >
                        Voir tout
                    </a>
                </div>
            </div>
        </div>
        <div className="card card-datatable table-responsive">
            {/* <table class="dt-route-vehicles table">
                <thead>
                    <tr>
                        {elementsHeadTable.map(e=>  <th> {e} </th>  )}
                    </tr>
                    </thead>
                    <tbody>
                        
                 
                           {children}
                            
                       
                            
                          


                    </tbody>
               
            </table> */}


<DataTable paginator  showGridlines rows={20} header={header}  value={elements}   filters={filters}  tableStyle={{ minWidth: '50rem' }}  globalFilterFields={['ref', 'name','email','phone','city','role','status']}  filterDisplay="row" emptyMessage="No customers found.">
{children}

</DataTable>
        </div>
    </div>
</div>
}