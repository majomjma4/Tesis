(()=>{
    const modal=document.querySelector('#importUsersModal'),userModal=document.querySelector('#userModal'),form=document.querySelector('#importUsersForm'),config=document.querySelector('#adminUsersConfig'),confirmBox=document.querySelector('#importConfirm');
    if(!modal||!form||!config||!confirmBox)return;
    const panels=[...form.querySelectorAll('[data-import-step]')],steps=[...form.querySelectorAll('[data-import-step-button]')],message=document.querySelector('#importMessage'),columnsHelp=document.querySelector('#importColumnsHelp');
    let current=1,previewData=null;
    const showStep=step=>{current=step;panels.forEach(panel=>panel.hidden=Number(panel.dataset.importStep)!==step);steps.forEach((item,index)=>item.classList.toggle('active',index===step-1));message.hidden=true;};
    const showRoleFields=()=>{const student=form.elements.role.value==='student';form.querySelectorAll('.import-student').forEach(el=>el.hidden=!student);form.querySelectorAll('.import-teacher').forEach(el=>el.hidden=student);columnsHelp.innerHTML=student?'Columnas para estudiantes: <strong>nombre, correo, cédula y usuario opcional</strong>.':'Columnas para docentes: <strong>nombre, correo, cédula y título académico opcional</strong>.';previewData=null;};
    const syncDialogs=()=>window.syncAdminUserDialogs?.();
    const open=()=>{userModal.hidden=true;modal.hidden=false;form.reset();showRoleFields();showStep(1);syncDialogs();requestAnimationFrame(()=>form.elements.role.focus());};
    const close=()=>{modal.hidden=true;confirmBox.hidden=true;form.reset();previewData=null;showRoleFields();showStep(1);syncDialogs();};
    const payload=async mode=>{const data=new FormData(form);data.set('mode',mode);const file=form.file.files[0];if(file&&form.content.value.trim()==='')data.set('content',await file.text());data.delete('file');return data;};
    const send=async mode=>{const response=await fetch(config.dataset.import,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:await payload(mode)});const result=await response.json().catch(()=>({success:false,message:'La respuesta del servidor no es válida.'}));if(!response.ok||!result.success)throw new Error(result.message||'No fue posible procesar la lista.');return result;};
    const cell=(row,value,className='')=>{const td=document.createElement('td');td.textContent=String(value??'');if(className)td.className=className;row.append(td);};
    const render=()=>{
        const reviewBody=form.querySelector('[data-import-rows]'),finalBody=form.querySelector('[data-import-final-rows]');reviewBody.textContent='';finalBody.textContent='';
        previewData.rows.forEach(item=>{const row=document.createElement('tr');cell(row,item.line);cell(row,item.name);cell(row,item.email);cell(row,item.username||'No aplica');cell(row,item.code);cell(row,item.password);cell(row,item.valid?'Correcto':item.error,item.valid?'import-row-valid':'import-row-invalid');reviewBody.append(row);const finalRow=document.createElement('tr');[item.name,item.email,item.username||'No aplica',item.code,item.password].forEach(value=>cell(finalRow,value));finalBody.append(finalRow);});
        const labels={student:'Estudiantes',teacher:'Docentes'},summary=form.querySelector('[data-import-config-summary]'),details=[['Tipo de usuarios',labels[previewData.config.role]],['Carrera',previewData.config.career],['Periodo',previewData.config.period],['Semestre',previewData.config.role==='student'?`${previewData.config.semester}.º`:'No aplica'],['Asignación como tutor',previewData.config.role==='teacher'?(previewData.config.can_tutor?'Sí':'No'):'No aplica'],['Cuentas por crear',previewData.total]];
        summary.replaceChildren(...details.map(([label,value])=>{const wrapper=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;dd.textContent=String(value);wrapper.append(dt,dd);return wrapper;}));
    };
    document.querySelector('#importUsersButton')?.addEventListener('click',open);
    document.querySelectorAll('[data-close-import]').forEach(button=>button.addEventListener('click',close));
    modal.addEventListener('click',event=>{if(event.target===modal)close();});
    form.elements.role.addEventListener('change',showRoleFields);
    form.addEventListener('input',event=>{if(current===1&&event.target.name!=='_csrf')previewData=null;});
    form.querySelectorAll('[data-import-next]').forEach(button=>button.addEventListener('click',async()=>{
        if(current===1){button.disabled=true;message.hidden=true;try{const result=await send('preview');previewData=result.data;render();showStep(2);if(previewData.invalid>0){message.className='users-message error';message.textContent=`Hay ${previewData.invalid} fila(s) con errores. Corrige la lista antes de continuar.`;message.hidden=false;}}catch(error){message.className='users-message error';message.textContent=error.message;message.hidden=false;}finally{button.disabled=false;}}
        else if(current===2&&previewData){if(previewData.invalid>0){message.className='users-message error';message.textContent='Regresa y corrige las filas marcadas antes de continuar.';message.hidden=false;return;}showStep(3);}
    }));
    form.querySelectorAll('[data-import-back]').forEach(button=>button.addEventListener('click',()=>showStep(Math.max(1,current-1))));
    steps.forEach(button=>button.addEventListener('click',()=>{const target=Number(button.dataset.importStepButton);if(target<current){showStep(target);return;}if(target===2&&previewData){showStep(2);return;}if(target===3&&previewData&&previewData.invalid===0){showStep(3);return;}message.className='users-message error';message.textContent=target===2?'Completa la configuración y pulsa Siguiente para revisar la lista.':'Revisa una lista válida antes de pasar a la verificación final.';message.hidden=false;}));
    form.querySelector('[data-import-create]').addEventListener('click',()=>{if(previewData){confirmBox.hidden=false;syncDialogs();}});
    confirmBox.querySelector('[data-cancel-import-confirm]').addEventListener('click',()=>{confirmBox.hidden=true;syncDialogs();});
    confirmBox.querySelector('[data-accept-import-confirm]').addEventListener('click',async event=>{event.currentTarget.disabled=true;try{const result=await send('import');message.className='users-message success';message.textContent=result.message;message.hidden=false;confirmBox.hidden=true;syncDialogs();setTimeout(()=>location.reload(),700);}catch(error){confirmBox.hidden=true;syncDialogs();message.className='users-message error';message.textContent=error.message;message.hidden=false;}finally{event.currentTarget.disabled=false;}});
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!confirmBox.hidden){confirmBox.hidden=true;syncDialogs();}else if(event.key==='Escape'&&!modal.hidden)close();});
})();
