const fs = require('fs');
const path = require('path');

const fileLoc = path.join(__dirname, '../src/data/mockReportes.ts');
let content = fs.readFileSync(fileLoc, 'utf8');

// The file exports `export const mockReportes: Reporte[] = [ ... ]`
// Let's isolate the JSON array part. Make sure not to break the interface.
const startIdx = content.indexOf(`export const mockReportes`);
const bracketStart = content.indexOf(`[`, startIdx);
const bracketEnd = content.lastIndexOf(`]`);

const definitions = content.substring(0, startIdx);
const arrayStr = content.substring(bracketStart, bracketEnd + 1);

// Parse as JSON but some keys might be unquoted or we might have trailing commas depending on ts file,
// actually the file was generated or written safely so we evaluate it safely.
let reports;
try {
    // Basic trick to parse TS array of objects if it is JSON-like
    // but the file might have comments.
    const cleanStr = arrayStr
        .replace(/\/\/.*$/gm, '') // remove line comments
        .replace(/\/\*[\s\S]*?\*\//gm, ''); // remove block comments
        
    // using Function instead of eval for slight safety, though it's local mocks
    reports = new Function('return ' + cleanStr)();
} catch (e) {
    console.error("Failed to parse the mock data:", e);
    process.exit(1);
}

// Now we inject the data.
reports.forEach(r => {
    // horaSalidaBase: usually 30mins to 1h before fechaHoraInicio
    const start = new Date(r.fechaHoraInicio);
    if (!isNaN(start.getTime())) {
        r.horaSalidaBase = new Date(start.getTime() - (Math.random() * 30 + 30) * 60000).toISOString().slice(0, 16);
        r.horaLlegadaSitio = new Date(start.getTime() - (Math.random() * 10 + 5) * 60000).toISOString().slice(0, 16);
    }

    if (r.estatusReporte === 'Cerrado / Trabajo Finalizado' && r.fechaHoraFin) {
        const end = new Date(r.fechaHoraFin);
        if (!isNaN(end.getTime())) {
            r.horaRegresoABase = new Date(end.getTime() + (Math.random() * 60 + 30) * 60000).toISOString().slice(0, 16);
        }
    }
    
    // Retrabajo? ~15% chance
    r.esRetrabajo = Math.random() < 0.15;
    
    // Descartado? Overwrite status for ~5% chance
    if (Math.random() < 0.05) {
        r.estatusReporte = 'Descartado / Cancelado';
    }
});

// Stringify and piece it back together
const newArrayStr = JSON.stringify(reports, null, 4);

fs.writeFileSync(fileLoc, definitions + `\nexport const mockReportes: Reporte[] = \n` + newArrayStr + `;\n`);

console.log("Patched", reports.length, "reports successfully with Phase 7 fields.");
