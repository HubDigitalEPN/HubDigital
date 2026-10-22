const solapa = (a,b,m=5) => a.x < b.x+b.w+m && a.x+a.w+m > b.x && a.y < b.y+b.h+m && a.y+a.h+m > b.y;
const tocaYema = (a,p) => Math.hypot(p.x-Math.max(a.x,Math.min(a.x+a.w,p.x)),p.y-Math.max(a.y,Math.min(a.y+a.h,p.y))) < p.tamano+7;

/** Etiquetas próximas a su yema: ninguna caja tapa otra caja ni una yema visible.
 * El segmento de unión termina exactamente en el borde de ambas figuras.
 */
export function distribuirEtiquetasTaxonomicas(candidatos,yemas,ancho,alto,reservadas=[]) {
    const cajas=[...reservadas],resultado=[];
    for(const p of candidatos){
        if(resultado.length>=36)break;
        const w=Math.min(ancho-12,p.w),h=42,r=p.tamano+10,opciones=[];
        for(const extra of [0,24,48,80])for(const [dx,dy] of [[0,1],[0,-1],[1,0],[-1,0],[1,1],[-1,1],[1,-1],[-1,-1]]){
            const x=p.x+(dx?dx*(r+extra+w/2):0)-w/2,y=p.y+(dy?dy*(r+extra+h/2):0)-h/2;
            const a={x,y,w,h};
            if(x<6||y<6||x+w>ancho-6||y+h>alto-6||cajas.some(b=>solapa(a,b))||yemas.some(b=>tocaYema(a,b)))continue;
            const extremo={x:Math.max(x,Math.min(x+w,p.x)),y:Math.max(y,Math.min(y+h,p.y))};
            opciones.push({...a,extremo,distancia:Math.hypot(extremo.x-p.x,extremo.y-p.y)});
        }
        opciones.sort((a,b)=>a.distancia-b.distancia);
        const sitio=opciones[0];if(!sitio)continue;
        const dx=sitio.extremo.x-p.x,dy=sitio.extremo.y-p.y,d=Math.hypot(dx,dy)||1;
        const inicio={x:p.x+dx*p.tamano/d,y:p.y+dy*p.tamano/d};
        cajas.push(sitio);resultado.push({...p,...sitio,inicio});
    }
    return resultado;
}
