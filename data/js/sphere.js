    function generate() {
        var radius=parseFloat(document.getElementById("txtRadius").value);
        var fill=document.getElementById("chkFill").checked;
        var hints=document.getElementById("chkHints").checked;
        var middle=document.getElementById("chkMiddle").checked;
        //printBlocks(radius,middle,fill,hints);
        //var blocks=generateBlocks(radius);
        //printBlocks(blocks);
        
            //printBlocks(topRadius,bottomRadius,height,middle,fill,hints);
        var blocks=generateBlocks(radius,middle);
        if (!fill)
            blocks=purgeBlocks(blocks);
        printBlocks(blocks,hints);
        //printBlocks(blocks);
    }
    
    function generateBlocks(radius,middle) {
        var radiusSq=radius*radius;
        var halfSize,size,offset;
        if (middle) {
            size=(2*Math.ceil(radius))+1;
            offset=Math.floor(size/2);
        } else {
            halfSize=Math.ceil(radius)+1;
            size=halfSize*2;
            offset=halfSize-0.5;
        }
        function isFull(x,y,z) {
            x-=offset;
            y-=offset;
            z-=offset;
            x*=x;
            y*=y;
            z*=z;
            return x+y+z < radiusSq;
        }
        var blocks=[];
        for (var z=0; z<size; z++) {
            var slice=blocks[z]=[];
            for (var x=0; x<size; x++) {
                var row=slice[x]=[];
                for (var y=0; y<size; y++) {
                    row[y]=isFull(x,y,z);
                }
            }
        }
        return blocks;
    }
    
    function purgeBlocks(blocks) {
        var newblocks=[];
        for (var z=0; z<blocks.length; z++) {
            var slice=blocks[z];
            var newslice=newblocks[z]=[];
            for (var x=0; x<slice.length; x++) {
                var row=slice[x];
                var newrow=newslice[x]=[];
                for (var y=0; y<row.length; y++) {
                    newrow[y]=row[y] && (
                        !row[y-1] ||
                        !row[y+1] ||
                        !slice[x-1][y] ||
                        !slice[x+1][y] ||
                        !blocks[z-1][x][y] ||
                        !blocks[z+1][x][y]
                    );
                }
            }
        }
        return newblocks;
    }
    
    function printBlocks(blocks, hints) {
        var x,y,z;
        var table,tbody,tr,td,prev,divNeeded,needed=0;
        var sphere=document.getElementById("sphere");
        while (sphere.lastChild)
            sphere.removeChild(sphere.lastChild);
        sphere.appendChild(divNeeded=document.createElement("p"));
        sphere.appendChild(document.createElement("div"));
        var prevslice=blocks[0];
        for (z=1; z<blocks.length-1; z++) {
            var slice=blocks[z];
            sphere.appendChild(table=document.createElement("table"));
            sphere.appendChild(document.createElement("div"));
            table.appendChild(tbody=document.createElement("tbody"));
            table.border=1;
            table.cellpadding=0;
            table.cellspacing=0;
            for (x=0; x<slice.length; x++) {
                var row=slice[x];
                tbody.appendChild(tr=document.createElement("tr"));
                for (y=0; y<row.length; y++) {
                    tr.appendChild(td=document.createElement("td"));
                    prev=hints && prevslice[x][y];
                    if (row[y]) {
                        td.className=prev?"prev_full":"full";
                        needed++;
                    } else {
                        td.className=prev?"prev_empty":"empty";
                    }
                    td.appendChild(document.createElement("div"));
                }
            }
            prevslice=slice;
        }
        divNeeded.appendChild(document.createTextNode("Blocks needed: "+needed));
    }
    
    function oldPrintBlocks(radius,middle,fill,hints) {
        var radiusSq=radius*radius;
        var halfSize,size,offset;
        if (middle) {
            size=(2*Math.ceil(radius))+1;
            offset=Math.floor(size/2);
        } else {
            halfSize=Math.ceil(radius)+1;
            size=halfSize*2;
            offset=halfSize-0.5;
        }
        var sphere=document.getElementById("sphere");
        while (sphere.lastChild)
            sphere.removeChild(sphere.lastChild);
        
        function isFull(x,y,z) {
            x-=offset;
            y-=offset;
            z-=offset;
            x*=x;
            y*=y;
            z*=z;
            return x+y+z < radiusSq;
        }
        
        var x,y,z;
        var table,tbody,tr,td,prev,divNeeded,needed=0;
        sphere.appendChild(divNeeded=document.createElement("p"));
        sphere.appendChild(document.createElement("div"));
        for (z=1; z<size-1; z++) {
            sphere.appendChild(table=document.createElement("table"));
            sphere.appendChild(document.createElement("div"));
            table.appendChild(tbody=document.createElement("tbody"));
            table.border=1;
            table.cellpadding=0;
            table.cellspacing=0;
            for (x=0; x<size; x++) {
                tbody.appendChild(tr=document.createElement("tr"));
                for (y=0; y<size; y++) {
                    tr.appendChild(td=document.createElement("td"));
                    prev=hints && isFull(x,y,z-1);
                    if (isFull(x,y,z)) {
                        if (
                            !fill &&
                            isFull(x-1,y,z) &&
                            isFull(x+1,y,z) &&
                            isFull(x,y-1,z) &&
                            isFull(x,y+1,z) &&
                            isFull(x,y,z-1) &&
                            isFull(x,y,z+1)
                        ) {
                            td.className=prev?"prev_open":"open";
                        } else {
                            td.className=prev?"prev_full":"full";
                            needed++;
                        }
                    } else {
                        td.className=prev?"prev_empty":"empty";
                    }
                    td.appendChild(document.createElement("div"));
                }
            }
        }
        divNeeded.appendChild(document.createTextNode("Blocks needed: "+needed));
    }