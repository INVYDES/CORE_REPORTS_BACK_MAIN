const fs = require('fs');
const path = require('path');

const reportesPath = path.join(__dirname, '../src/data/mockReportes.ts');
const encuestasPath = path.join(__dirname, '../src/data/mockEncuestas.ts');

let reportesContent = fs.readFileSync(reportesPath, 'utf8');
let encuestasContent = fs.readFileSync(encuestasPath, 'utf8');

const deps = [1, 2, 3];
const cats = ['Mantenimiento Preventivo', 'Mantenimiento Correctivo', 'Instalación', 'Mejora', 'Diagnóstico'];
const usersPerDep = {
    1: [1, 4, 5, 6, 7],
    2: [8, 11, 12, 13, 14],
    3: [15, 18, 19, 20, 21]
};

let newReports = [];
let newEncuestas = [];
let nextReportId = 50; 
let nextEncuestaId = 2; 
let nextFolioNum = 50;

for (let i = 0; i < 40; i++) {
    const dep = deps[Math.floor(Math.random() * deps.length)];
    const creators = usersPerDep[dep];
    const creator = creators[0]; 
    const executor = creators[Math.floor(Math.random() * (creators.length - 1)) + 1]; 
    
    const cat = cats[Math.floor(Math.random() * cats.length)];
    const start = new Date(2026, 0, 1);
    const end = new Date(2026, 2, 10);
    const d1 = new Date(start.getTime() + Math.random() * (end.getTime() - start.getTime()));
    const d2 = new Date(d1.getTime() + (Math.random() * 4 + 1) * 60 * 60 * 1000); 
    const d3 = new Date(d2.getTime() + (Math.random() * 2 + 1) * 60 * 60 * 1000); 
    
    const isFinished = Math.random() > 0.2; 
    const reportType = Math.random() > 0.5 ? 'Libre' : 'Ticket';
    const originId = reportType === 'Libre' ? null : Math.floor(Math.random() * 15) + 1;

    const rep = {
        idReporte: nextReportId++,
        folio: `REP-2026-${String(nextFolioNum++).padStart(3, '0')}`,
        idDependencia: dep,
        creadoPor: creator,
        tipoReporte: reportType,
        categoria: cat,
        origenDatosId: originId,
        fechaElaboracion: d1.toISOString().slice(0, 16),
        responsableId: null,
        responsableTexto: "Usuario Generado Mock",
        cargoTexto: "Supervisor Mock",
        ejecutoresIds: [executor],
        fechaHoraInicio: d2.toISOString().slice(0, 16),
        fechaHoraFin: isFinished ? d3.toISOString().slice(0, 16) : "",
        desarrolloActividades: `Actividades generadas mock para testing. Categoría: ${cat}.`,
        materiales: [],
        evidenciaFotografica: [],
        conclusionTrabajo: isFinished ? "Trabajo completado exitosamente." : "",
        estatusReporte: isFinished ? "Cerrado / Trabajo Finalizado" : "Abierto / Trabajo Parcial"
    };
    newReports.push(rep);

    if (isFinished && Math.random() > 0.6) {
        newEncuestas.push({
            idEncuesta: nextEncuestaId++,
            idReporte: rep.idReporte,
            calificacion: Math.floor(Math.random() * 5) + 6, 
            comentarios: "Buen servicio, generado automáticamente.",
            nombreFirma: "Firma Automatizada",
            fecha: new Date(d3.getTime() + 1000 * 60 * 60).toISOString().slice(0, 16)
        });
    }
}

const lastBracketIndexReportes = reportesContent.lastIndexOf("]");
const stringifiedReports = newReports.map(r => JSON.stringify(r, null, 4)).join(',\n        ');
reportesContent = reportesContent.substring(0, lastBracketIndexReportes) + "        ,\n        " + stringifiedReports + "\n" + reportesContent.substring(lastBracketIndexReportes);
fs.writeFileSync(reportesPath, reportesContent);

const lastBracketIndexEncuestas = encuestasContent.lastIndexOf("]");
const stringifiedEncuestas = newEncuestas.map(r => JSON.stringify(r, null, 4)).join(',\n    ');
encuestasContent = encuestasContent.substring(0, lastBracketIndexEncuestas) + "    ,\n    " + stringifiedEncuestas + "\n" + encuestasContent.substring(lastBracketIndexEncuestas);
fs.writeFileSync(encuestasPath, encuestasContent);

console.log("Appended", newReports.length, "reports and", newEncuestas.length, "encuestas.");
