/**
 * WorkManager ERP - Citas Médicas JavaScript
 * Sistema completo de gestión de citas médicas con IA
 */

let currentStep = 'paciente';
let selectedPaciente = null;
let selectedMedico = null;
let selectedHorario = null;
let iaChatSession = null;

// Inicialización
document.addEv000);
} 5    },    }
();
    ovet.rem aler  {
         Element) ent (alert.par  if> {
       =out(()etTimendos
    sde 5 segus espué-remover d    // Autod);
    
il.firstChcontainerlert, Before(anserter.iain    contt.body;
cumen') || doent.main-contrySelector('document.que = nerontait cy
    constas o al bode alernedor dl conte// Agregar a
    
     `;
   "></button>ove()ement.remis.parentElnclick="the" otn-clos"bton" class=="buton type<butte}
           ${messag`
     erHTML =  alert.innble`;
   dismissirt-type} aleert alert-${ `alclassName =
    alert.('div');eElementeatt.crumen= docnst alert 
    coalmporlerta te/ Crear ao') {
    /pe = 'inf tyge,wAlert(messa sho

function';
} = 'nonetyle.display(modalId).stByIdmennt.getEle docume{
   odalId) odal(mnction hideMx';
}

fu 'flelay =).style.dispalIdod(mElementByIdett.g    documen {
lId)al(modaowModion shnctlidad
fuiones de uti}

// Func;
nfo')', 'iad...lidespecia de la nformación'Cargando iowAlert(    shpecialidad
da de la estallación deformar in Mostra   //{
 dId) daecialialidad(esperEspeciunction v;
}

fialidad')specaso('e  siguientePaCita');
  Nuevl('modal    showModa  }
     }
  break;
              edicos();
 cargarM             true;
 =edtion.select op           ))) {
erCase(dad.toLows(especialise().includeerCa.toLowtexton.    if (options) {
    Select.optilidadn of especia(let optio
    for idadCita');cialById('espegetElement= document.dSelect alidaspeci   const eita
 de cmulario  en el forspecialidadcionar e-selecPre/ ) {
    /lidadpeciasta(esrEspecialibusca
function 
ticket
} modal de n o redireccióplementarIm
    // );, 'info'et...' tick deción a creairigiendoert('Red
    showAlIA() {cketDesdeion crearTires
functuxiliaFunciones a
// ock';
}
ay = 'blyle.displntainer.stCospuesta   re
    
 ;
    }ay = 'none'ispls.style.desSugeridaccion a      } else {
 ;
     = 'block'tyle.displayugeridas.sonesSci    ac
    ML = html;HTnes.innerotonesAccio       b       
    }
      tton>';
cket</bur Ti"></i> Creat-alticke"fas fa-tclass=i "><etDesdeIA()Tickrearick="concl-2" rning men btn-waclass="bttton bu '< +=ml ht           ticket) {
ar_.creesaccionta.      if (da    
     }
    
     /button>';ndar Cita<> Ageus"></iplfa-calendar-as ="fsslaa\')"><i caCitNuevdaldal(\'moowMo"sh=-2" onclickary metn-prim bbtns="<button clas= ' html +           r_cita) {
daagencciones. if (data.a    
    
       html = '';     let  > 0) {
   ).lengthta.acciones(days&& Object.kes .accione  if (data    
  spuesta;
 data.reL =rHTMesta.inneidoRespu
    conten;
    ')nesAccionestById('botont.getElemenmes = docuccionetonesA   const bo
 as');idionesSugerById('accgetElement= document.sSugeridas nst accioneA');
    coespuestaIcontenidoRyId('mentBent.getEleesta = documpuntenidoResnst co cotaIA');
   ('respuestByIdnt.getElemendocumentainer = puestaCo  const res
  ata) {spuestaIA(don mostrarRe
functi   });
}
r');
 rroulta', 'ear consl procesor aert('ErrwAl       sho error);
 or('Error:', console.err    {
    (error =>  .catch })
  
      }
     ror');sulta', 'eresar conror al proc'Erge || .messart(dataleshowA   
         else {   }    data);
  RespuestaIA(strar          mo
  s) {esta.succ(da  if => {
      (data en .th
   e.json())sponsse => ren(respon)
    .theata
    }mD   body: for
     : 'POST',ethod m     p', {
  agenda.phh('citas- fetc
    
   one';splay = 'n').style.diestaIA'respumentById(ment.getEledocu
    
    c);
    }pacienteDoocumento', 'paciente_dta.append(mDa    forDoc) {
    nte(pacie
    if ensaje);, maje''mensappend(rmData.
    fo');ulta_iaon', 'cons('actimData.append
    for;w FormData()Data = nest form con     
 
 ;
    }  return   ;
   warning')', 'consultangresa una 'Por favor i  showAlert(      rim()) {
mensaje.tf (!
    
    ivalue;onsultaIA').('pacienteCtElementById document.genteDoc =t pacie;
    consalueo').vText'consultaIAd(ByItElementt.ge = documenjemensaonst 
    ctaIA() {sarConsulnction proceht;
}

fuscrollHeiginer.Contaessagp = mesllToner.scrogesContaissa  me
  Div);actionendChild(r.appsContaineage mess    
   ;
>'div html + '</' +e-content">messagiv class="/div><dt"></i><boro fa-lass="fasr"><i csage-avata="messs = '<div claiv.innerHTML    actionD
ssage';-me-message iachatsName = 'clasionDiv.   act('div');
 ateElementocument.crenDiv = dst actio conges');
   hatMessayId('iaC.getElementB = documentinerContamessagesconst  
       /div>';
l += '<
    htm  }
    ;
   `ton>a}</butridugecialidad_s.especcionesBuscar ${a"></i>  fa-user-mdfas class=""><ida}')sugericialidad_cciones.espeialista('${aEspecuscarck="bcliono" sm btn-infn-"btn bt class= `<button  html +=      gerida) {
d_suialidas.especne   if (accio    
 ;
    }
button> 'r Ticket</> Creat"></icket-altia-ass="fas fIA()"><i cletDesdeTick"crearclick=" onwarning btn-sm btn-ss="btnon cla= '<butt +    htmlet) {
    crear_tickones.cci (a    
    if';
    }
on> buttr Cita</Agendalus"></i> calendar-pss="fas fa-"><i cla')\alNuevaCitaodal(\'mod"showMick=nclimary" o-prbtntn btn-sm ass="bclton  '<buthtml += {
        r_cita)es.agendaon   if (acci  
 :</h5>';
  geridascciones Su><h5>Aeridas"nes-sug"accio'<div class=ml =  let htones) {
   (acciridasionesSugeostrarAccn mctio
}

funllHeight;r.scroesContainemessagTop = rollner.scesContai
    messagsageDiv);endChild(mesner.appgesContaimessa
    t);
    d(contenv.appendChilageDi    messar);
(avatendChildgeDiv.appssa    me
';
    '</p>+ mensaje + p>' : '<p>'  '</ensaje +"></i> ' + mfa-spinnner "fas fa-spiss=><i claing ? '<pHTML = isTypinner content.ent';
   e-cont'messagssName = ntent.cla   co;
 t('div')menElent.creatent = documeonst conte  
    c;
  i>'fa-user"></ass="fas  : '<i clot"></i>'obfas fa-r'<i class="=== 'ia' ? tipo = .innerHTML 
    avatarar';ge-avatme = 'messa.classNaatar');
    avment('diveEleent.creatr = documvatanst a  co   
  
 g' : ''}`;ing ? 'typin${isTypge -message ${tipo}messa = `chat-assNameeDiv.cl  messag');
  ement('divreateEl.cv = documentDist message    con 
);
   Messages'ChatId('iaementBycument.getEldo= sContainer t message{
    cons false) isTyping =nsaje, po, mesajeChat(tiagregarMennction fu


};
    }); nuevo.')r intenta dea. Por favo la consultarcesrror al proat('ia', 'EensajeChregarM       ag      }
   remove();
stMessage.        la) {
    ')('typingist.containsassLge.clMessalastge && (lastMessa      if tChild;
  s.lastElemenge message =lastMessat    conses');
     MessagById('iaChatetElementment.ges = docussag meston       citura
 e escrcador dRemover indi// 
        );', error('Error:ole.errorons      c
  => {atch(error    .c  })
 
  
        }');ta de nuevo. favor intena. Porr tu consulte procesa no pudLo siento,a', ''ijeChat(gregarMensa       ase {
         } el           }
);
     cionesacridas(data.ccionesSugerA     mostra           
gth > 0) {iones).lena.acc(datkeysject. Obiones && (data.acc       ify
      las haridas sies sugeaccion/ Mostrar           /      
  
      );spuestata.reia', da('ajeChatgregarMens        a
    ccess) {if (data.su      
     
     }       ve();
 e.remossag      lastMe      ')) {
ns('typingaisList.contsage.clase && lastMeslastMessagif (        ;
entChild.lastElemssagesge = meessat lastMcons     );
   Messages'iaChatd('ElementByIetcument.gssages = dot meons        c escritura
cador deRemover indi        // a => {
at   .then(d())
 onnse.js => respoonseen(resp.th
    ta
    })rmDa fo       body:
  'POST',thod:     me, {
   php'enda.itas-ag fetch('c 
   sion);
   aChatSes_id', iion'sessppend(mData.a for  aje);
  mensnsaje',mea.append('formDatia');
    nsulta_ 'cod('action',mData.appen
    for);mData(ew For nrmData = fost IA
    conlta a lasuviar con // En   
   , true);
 sulta...'alizando cont('ia', 'AnChaMensaje   agregarescritura
 e cador dar indi/ Mostr   /
 '';
    = ut.value     input
inpLimpiar    
    // );
 ', mensajeeChat('usergarMensajt
    agrehasuario al censaje del u Agregar m
    //
    aje) return; if (!mens   ;
    
rim()t.value.tnsaje = inpuonst meut');
    c('iaChatInpyIdgetElementBent.uminput = doc const {
   aIA() ultnviarConsnction e
fu);
}
+ Date.now(ion_' essSession = 's    iaChat() {
ializeIAChatnction init Médica
fuhat con IA;
}

// C;
    }) 'error')PQRS',r  al creart('Error     showAlerror);
   ', error('Error:.e   console   r => {
  ch(erro
    .cat })        }
 r');
  erroQRS', 'al crear Pr  || 'Errogedata.messashowAlert(           } else {
     S();
    loadPQR      );
      .reset(QRS')Id('formPntByemetElcument.gedo         QRS');
   al('modalP     hideMod;
       'success')te', xitosamenda et('PQRS crea   showAler       {
  ccess) (data.su       if 
 => { .then(data son())
   .jsponsese => reesponhen(r    .t
 })
   y: formData  bod,
      ST' method: 'PO    
   enda.php', {'citas-ag fetch(
    
   r_pqrs');tion', 'creapend('acormData.ap
    frmPQRS'));'foId(ementByment.getElocu FormData(data = new formDconst    QRS() {
 crearPnctionl;
}

fuML = htmnnerHT container.i';
   </div>  html += '
    
  `;
    });
            </div>     iv>
           </d
        >)}</png(StriateleD.toLocaat)reated_em.c Date(itnewrong> ${cha:</strong>Fest><   <p                tado}</p>
 ${item.esong> trado:</strong>Estp><s      <    
          </p>pcion}{item.descring> $</stroescripción:g>Dstron  <p><            </p>
      citante}oli_sbre${item.nomtrong> te:</stanciolip><strong>S           <      ">
   ntrs-conte="pqv class<di            
    iv>/d  <            pan>
  Case()}</soUpperitem.tipo.t>${tem.tipo}"ge-${idge bad"ba class=span         <      5>
     nto}</h- ${item.asuumero_pqrs} >${item.n   <h5          >
       rs-header" class="pq    <div        ">
    "pqrs-itemv class=    <di `
             html += {
   em =>ch(its.forEa
    pqr">';
    tpqrs-lisiv class="= '<dlet html 
    
        }   return;
';
     v>/di3><das</hstraRS regihay PQ/i><h3>No nts"><me-com fafas<i class="-state">s="emptyv clasdi       '<ML = 
     .innerHTontainer  c   {
   ngth === 0) s || pqrs.le (!pqr 
    ifiner');
   qrsContayId('petElementBnt.gr = docume containe
    constQRS(pqrs) {rPtraction mos

fun
    });
});, errorr PQRS:'or al cargaerror('Errnsole.
        co> {rror =h(etc .ca)
      }
    }
     ta.pqrs);rPQRS(daostra        m
    ss) {ata.succef (d        ia => {
.then(dat
    se.json()) => responen(response)
    .thrs'pq=get_.php?actioncitas-agenda('  fetchPQRS() {
  ad
function loón de PQRSti
}

// Ges);  }  }
        ita');
uevaCdal('modalN  showMo         ;
 nte)paciedo(data.eEncontracientarPa       mostr    mento;
 umero_docu.paciente.nvalue = data').entementoPaci('documentByIdgetEleument.     doc
       nte;ata.pacieaciente = dctedP   sele
         {s) uccesif (data.s      => {
   then(data))
    .ponse.json( => resen(response)
    .thienteId}`te&id=${pacget_pacienction=agenda.php?a`citas-fetch(   modal
  y abrir pacienteccionar re-sele P//  eId) {
  ente(pacientPaciendarCitaion ag

funct= html;
}erHTML er.inn   contain</div>';
 html += '    
    );

    }  `;        </div>
     div>
          </        utton>
            </b
           ial> Historory"></ist-hi"fas faclass=   <i                     })">
 paciente.idtorial(${="verHis" onclickm btn-info-s"btn btn class=<button                   button>
   </            
      ta Agendar Cis"></i>ar-pluas fa-calend"fass= <i cl                       .id})">
${pacienteaciente(agendarCitaP"lick=y" onc btn-primar btn-smss="btncla    <button                >
 ons"ctiiente-alass="pac  <div c                 </div>
           >
  '}</p registradoemail || 'Noaciente.trong> ${p</s>Email:strong  <p><         
         rado'}</p>ist| 'No reg.telefono | ${paciente</strong>eléfono:trong>T  <p><s          >
        to}</pocumeno_d.numernteacieg> ${ptron</s>Documento:ong<str     <p>           >
    s}</h4apellidoente.s} ${paciiente.nombre${pac    <h4>                nfo">
-iteacien"p <div class=              e-card">
 cientss="pa <div cla  
         = `  html +    > {
  paciente =rEach(ientes.fo 
    pac  ">';
 tes-gridcienlass="pa '<div ct html = 
    le    }
   return;
;
        div>'es</h3></aron pacientntr encoh3>No se"></i><as fa-search<i class="f-state">ass="empty cl   '<div      = 
    erHTMLiner.inn     conta) {
   === 0h engtcientes.l| pas |f (!paciente
    i   r');
 taineentesCon('paciementByIdt.getElumenoc = daineronst cont{
    cntes) (pacieacientestadosPmostrarResulction }

fun    });
'error');
cientes',  pa buscart('Error alhowAler      srror);
   e'Error:',rror(le.enso  co {
      ror =>ch(er
    .cat
    })    }';
    </div>cientes</h3>ntraron paco en<h3>No se"></i>searchs fa-s="faclasstate"><i "empty-class=iv   '<d              rHTML = 
nneiner').iContaespacientd('ementByIent.getEl       docum       } else {
     cientes);
 es(data.pantltadosPaciesustrarRe     mo      ss) {
 data.succef (
        i{ => .then(data))
    se.json( => respononsethen(respy)}`)
    .nent(querURICompoery=${encodes&quentescar_paciaction=bua.php?gend-a(`citasfetch
    
    n;
    }etur   r);
     warning'buscar', 'res para acteos 3 car men alngresahowAlert('I        s 3) {
y.length <|| query quer
    if (!).value;nte'acieuscarPementById('bgetElent.umy = docerconst qus() {
    scarPacientection bucientes
funda de pa/ Búsque;
}

/')...', 'infocientepal ial deo historandAlert('Carg
    showel paciente historial d veraralógica pmplementar   // InteId) {
  acieal(pstorirHinction vefu);
}

o'a...', 'infltconsuiciando wAlert('In  shonsulta
  a iniciar coarar lógica pmplement
    // ItaId) {ta(ciciarConsulniunction i
fase cit/ Acciones d

/ml;
} = hter.innerHTMLcontain    '</div>';
 html +=     
    });
;
     `>
       </div       </div>
               (1)}
     ado.sliceta.est() + cioUpperCaseharAt(0).to.cstad{cita.e      $          
    stado}">${cita.etus status-a-stass="agend <div cla           v>
        </di       div>
              </       
    button>    </                   
 riali> Histo"></istory-h fa="fas class    <i                     ">
   id})ciente_a.paial(${citverHistor onclick="-info"sm btn btn-="btnclassbutton            <            n>
 butto     </                   ar
> Iniciy"></is fa-pla"fass=cla      <i                 
      ">d})ta.i(${ciltainiciarConsuonclick="ccess" n-sm btn-subtn bt="ass <button cl                  ">
     ionsnda-act"age<div class=               >
     icado'}</pifespecNo  || 'ltasumotivo_con> ${cita.ong/stro:<trong>Motiv       <p><s       
      al'}</p>| 'Generbre |lidad_nomciata.espe> ${citrongdad:</specialing>Es  <p><stro              >
    nto}</pcumeero_dota.num ${cirong>stnto:</ong>Docume    <p><str                s}</h4>
lido ${cita.apelbres}a.nom<h4>${cit                    ntent">
-coass="agenda<div cl            
    div>         </       (0, 5)}
o.substringra_inici{cita.ho  $          >
        enda-time"ss="agdiv cla   <        }">
     ="${cita.iddata-cita-idnda-item" ss="age  <div cla        
  l += `        htm{
=> orEach(cita 
    citas.f
    e">';-timelins="agendav clas = '<ditml
    let h 
    }      return;
    `;
       </div>
           r</p>
   ra comenzapacita a nueva rograma un       <p>P    /h3>
     para hoy<amadas  progr hay citas<h3>No              
  /i>-times"><fa-calendar"fas lass= c        <i   
     tate">"empty-sss=   <div cla        `
 innerHTML = tainer.con      === 0) {
  h as.lengtititas || c(!cif 
    
    ntainer');aCogendtById('atElemenocument.geiner = d const conta {
   tas)ay(ciisplaDeAgendction updat);
}

fun  }rror);
  nda:', er agergar al caror('Erroe.er    consol    rror => {
catch(e })
    .      }
   gth;
  ta.citas.len datContent =nt').texHoyCou'citasById(.getElement    document       as);
 data.citDisplay(teAgenda upda      {
      ta.success)  if (da=> {
      n(data ))
    .thee.json(> respons =n(response   .the_hoy')
 _agendation=getphp?acas-agenda.cit
    fetch('ía AJAXla vs actualizarero podemoesde PHP, pa se carga dgend/ La a /da() {
   oadAgention lenda
funcn de ag
// Gestióive');
}
add('actlassList.te').cpacienById('step-etElement.g document));
   ve('active'remoist. step.classLach(step =>-step').forEorAll('.formySelect.quer document
   sorimer paostrar p// M  
    iente';
  acentStep = 'p
    curro = null;dHorariselectenull;
    = tedMedico ;
    seleculle = nacient  selectedP  
  ;
  s</p>'poniblehorarios disara ver  fecha pdico ymén a ucion>Selecuted""text-m'<p class=        erHTML = 
s').innnibleDispoiosorarentById('ht.getElemmen    docu;
one' = 'nisplaystyle.dnte').cieormNuevoPa'fId(entBy.getElemument';
    docplay = 'none).style.distrado'nteEnconpacieementById('etElcument.g   do
 a').reset();rmNuevaCit'fomentById(getEle document.a() {
   ormularioCitction resetF;
}

funrueturn t   re 
     }
   alse;
n fturre
        );arning'ario', 'wun horionar cceleebes sshowAlert('D   ) {
     ctedHorario  if (!sele  
     }
  e;
 alsreturn f     ng');
   rni 'waa fecha',ar unes seleccionebAlert('D    show    ) {
alue).va'aCit('fechIdByt.getElementcumenf (!do
    
    i};
    sefalreturn     ng');
    rniico', 'waun médeleccionar 'Debes sshowAlert(
        ue) {Cita').valyId('medicotElementBnt.ge (!docume  if}
    
  se;
    n fal    retur   );
 warning'ente', 'nuevo pacidatos del ar los e o complettentnte exiscar un pacieusebes bt('DowAler       shte()) {
 PacienDatosNuevo !validarPaciente &&edf (!select   i) {
 ularioCita(darFormli vaion}

funct
    });
'error');ndar cita', r al ageAlert('Erro       show, error);
 ror('Error:'ole.ernsco{
        ch(error =>  .cat    })
   
;
        }ror')ta', 'er cir al agendar'Erromessage || ta.owAlert(da     sh         } else {
();
      endaadAg lo        ta();
   ormularioCi     resetF       ;
lNuevaCita')modadal('Mo  hide         uccess');
 's, e'exitosamenta agendada CitwAlert('     sho     
  success) {data.        if (=> {
then(data ))
    .nse.json(spo> rense =respo.then(  })
    a
  Datdy: form
        bo',POST method: '
       ', {nda.phpcitas-agefetch('
      
  .value);')lta'motivoConsud(ElementByInt.get documea',sultonvo_cpend('moti.apformData    _fin);
.horaHorariotedselecin', 'hora_fata.append(   formD_inicio);
 ora.hctedHorarioleo', seora_iniciend('happ  formData.
  value);).echaCita'd('fntByIt.getEleme', documen_citad('fechaena.appormDat;
    fue)adCita').val'especialidId(tElementByocument.geidad_id', dpecialnd('esormData.appeue);
    f').valta('medicoCiElementByIddocument.getd', d('medico_irmData.appenta
    foa citos de l  // Da  
      }
  );
.valueciente')('emailPatByIdetElemencument.g doail',.append('emformData;
        te').value)efonoPacienId('telntBynt.getElemeono', documeefpend('telta.ap    formDaue);
    al').vPaciente('apellidosIdElementBycument.get, dollidos'.append('apeormData   fe);
     ente').valubresPacintById('nometElemedocument.gombres', d('nappenmData.
        for;e)ente').valuciPaentotById('documElemencument.geto', doment'numero_docud(ata.appenmDor
        fo', 'CC');ipo_documentappend('t   formData.
     , '1');e'acientend('crear_pppData.aorm f
       entevo paciCrear nue   //      e {
} els;
    e.id)cientectedPa_id', selenteppend('paciata.a   formD
     nte) {Pacieed(select    if nte
cieos del pa // Dat  
   ita');
  gendar_c', 'a('actionmData.appendfor);
    mData(= new ForformData    const }
    
     return;
       Cita()) {
 arFormulario if (!valida() {
   ndarCition age
functitagendar c

// A
}oraFin }; hora_fin: ho,cihoraIniinicio: { hora_Horario = cted);
    seled('selected'st.ad.classLiarget.tevent
    rarioo hoevonar nu  // Selecci  );
    
);
    }('selected'List.removelasslot.c        s {
h(slot =>orEaclected').fseslot.horario-ctorAll('.eleySment.quer
    docun anteriorciór selecveRemo) {
    // horaFincio, (horaIninarHorarion seleccio
functio
}
L = html;.innerHTMtainer
    conv>';ml += '</di    
    ht   });
`;
       iv>
           </d'}
   mall>' : 'cupado</small>O'<br><sdo ?    ${isOcupa        }
     iciora_ino.ho   ${horari  
           }')`}">.hora_fin{horarioinicio}', '$o.hora_orari{harHorario('$`seleccion : pado ? ''${isOcuck="      oncli      
     Estado}" claseio-slot ${rar"hos= <div clas    `
       html +=            
     ado' : '';
do ? 'ocupOcupais= aseEstado  const cldo;
       .ocupaario hordo =onst isOcupa     c
   ario => {h(hors.forEac  horario  ';
    
">rid-ghorariosss="v cladi><h5es</oniblarios Disph5>Horl = '<   let htm
    
   }  eturn;
 r';
       fecha</p>ra esta bles pas disponiario hay horing">No"text-warnass=ML = '<p clerHTainer.inn     cont== 0) {
   th =ngrios.leos || hora!horari
    if (   
 sponibles');iosDirarhoId('ByetElement= document.gtainer con const  {
   rarios)bles(hoponiariosDismostrarHor

function 
};
    });es</p>'niblios dispoorar cargar h alger">Error="text-dan'<p class         L = 
   HTMneres').insponibl'horariosDientById(t.getElemcumen    do   
 , error);'Error:'ror(  console.er
      rror => {    .catch(e})
    }
 ';
       s</p>bleos disponirari ho al cargarnger">Errorss="text-dap cla        '<
        ML = nerHTbles').inriosDisponi'horamentById(t.getEle  documen       else {
        } es);
   oniblspios_didata.horars(blesDisponiario mostrarHor
           cess) {suc   if (data. {
     ata =>    .then(d.json())
esponseonse => resp   .then(r)
   }
  dId}`ialida{espec_id=$alidadha}&especia=${fec}&fechd=${medicoIdd&medico_iidaonibildisp=buscar_dy: `action   bo       },
    ed',
  urlencodx-www-form-n/plicatio'ap-Type': tentCon '           aders: {
   he',
      'POSTethod:        mphp', {
nda.tas-ageci   fetch('
 
    es...</p>';os disponiblndo horari>CargaL = '<ps').innerHTMsponibleiosDi('horartByIdgetElemendocument.   
    
 rn;
    }        retules</p>';
sponibhorarios dia ver y fecha parco n médicciona umuted">Selet-tex"'<p class=           rHTML = 
 ).innebles'poni'horariosDisentById(Elemdocument.get        {
fecha) | !icoId |ed
    if (!malue;
    ).vita'dCida('especialementByIdument.getEldadId = docciali const espe;
   ).valueCita'yId('fechaentBetElem document.gst fecha =    con').value;
icoCita('medntByIdtElemeument.ged = docdicoIconst mead() {
    ponibilidrgarDis canctionhorarios
fulidad y  disponibi Gestión de//});
}

;
    os</option>'gar médicor al car="">Erruen valptio'<oTML = rHect.innemedicoSel    r);
    roor:', er.error('Err   console=> {
     rror catch(e   })
    .
 
        }>';ptionibles</odisponhay médicos e="">No option valu= '<nnerHTML Select.i    medico
          } else {});
              ;
    ld(option)ct.appendChiSele     medico           ita || 30;
ion_cduracn = medico.aset.duracioption.dat        o      `;
  pellidos}.aes} ${medicoedico.nombrr. ${montent = `Doption.textC              o.id;
  edic = mon.value      opti         option');
 eElement('cument.creatn = doonst optio  c              => {
 icoh(meddicos.forEacta.me    da;
        /option>' médico...<>Seleccionar"n value="optio'<erHTML = ct.innSele medico          {
 a.success) dat    if (=> {
    then(data    ..json())
 > responsee =(responshen    .tdId}`)
idaspecial_id=${ead&especialidost_medic=gehp?actiontas-agenda.pfetch(`ci  ;
    
  ion>'cos...</optrgando médi">Ca"ue=val'<option rHTML = ect.inne medicoSel}
    
   turn;
    
        re;>'</optionecialidad una esp seleccionameroPri">value="'<option HTML = lect.innericoSe     meddId) {
   lidaespecia    if (!
    
);medicoCita'entById('ent.getElem= documSelect edico  const m;
  alue).vlidadCita'ia('especlementByIdetEent.gId = documdadst especiali   con{
 cos() n cargarMedinctio médicos
fulidades yiaecstión de espGeal
}

// ip princl formulario eá ennto ya est documempo  // El ca;
  nte').valueentoPacieyId('documementBetElment.gmento = docuonst docu
    cmento el docure-llenar   // P   
 
  = 'block';e.displayyloPaciente.stormNuevone';
    f'nsplay = .style.diteEncontrado    pacien');
    
PacienteformNuevontById('lemeument.getEe = docuevoPacientormN   const fado');
 contrEnnte'pacieementById(t.getElen documcontrado =cienteEnconst pa
    ll;
    = nuPaciente ted   selecente() {
 rioNuevoPacitrarFormulaction mos

fun';
}= 'nonesplay yle.distiente.oPac  formNuevck';
  = 'bloisplay rado.style.dontcienteEnc    
    pa>
    `;
ado'}</small'No registrail || te.eml: ${pacienmaismall>E  <  l><br>
    rado'}</smal registono || 'Nolef{paciente.tefono: $l>Telésmal    <   r>
 o}</small><bcumentnumero_dopaciente.nto: ${ll>Documesma        <r>
><bos}</strongellidiente.ap ${pacres}ombciente.n{parong>$
        <strHTML = `nnee.iPacientos   dat
   
  aciente');NuevoPformById('lementgetEment.e = docuntaciermNuevoPt fo
    consontrado');ienteEnctosPacdad('ementByIt.getEl documenente =sPaciconst dato    trado');
nteEnconieId('pactElementByment.geado = docucontrienteEnt pacns
    co  ente;
  e = paciientelectedPacte) {
    spaciendo(nteEncontrastrarPacie mo

function;
} });
   e', 'error')pacientbuscar  al Alert('Error show     or);
  Error:', errr('nsole.erro   co => {
     .catch(error  })
    }
          
Paciente();ioNuevormular  mostrarFo      else {
    
        } aciente);rado(data.pnteEncontierPacstra       mo
     .paciente) {data.success && data       if (ta => {
    .then(da())
 ponse.json resonse =>.then(resp    })
    }`
o)ocumentComponent(dURIo=${encodedocumentaciente&car_pction=bus body: `a },
       d',
       lencodeur-www-form-cation/xppli': 'antent-Type  'Co
           headers: {      'POST',
 od:    meth    a.php', {
 agendetch('citas-    f   
 }
 return;
   ;
        g') 'warninumento',ocnúmero de dingresa un or favor rt('P     showAleo) {
   umentdoc  if (!lue;
  .vaoPaciente')d('documentlementByItEent.geocumcumento = d  const dodal() {
  nteMouscarPacieon bfuncties
 pacientdeión  gesta yuedúsq

// Bs;
}do && apelli nombresurn   
    retue;
 ciente').valPados('apellimentByIdment.getEleos = docuonst apellid
    ce;iente').valu('nombresPacntByIdemeument.getEl= docnst nombres     
    coalse;
return fone') ay === 'ntyle.displnte.suevoPacie  if (formN
  nte');evoPacieormNu('ftByIden.getEleme = documentvoPacientmNue const fornte() {
   NuevoPacietos validarDa

function    }
}rue;
eturn t          rt:
    defaulio;
      edHorarectel && sCita').valueyId('fechaementBment.getEln docu       retur     o':
ase 'horari     c
   .value;Cita')dicomelementById('cument.getElue && doita').vaadCespecialidId('entBytElemt.gemen docuturn      re:
      ecialidad' case 'esp   );
    ciente(PaNuevolidarDatos|| va null aciente !==ectedP  return sel
          te':pacien    case 'ep) {
    rentStwitch(curl() {
    stuaoAclidarPasction va
fun
= paso;
}urrentStep  c;
   ive').add('act`).classListep-${paso}Id(`stentByElemt.get    documenactive');
ove('List.rem`).classentStep}rrtep-${cuntById(`slemecument.getE
    dopaso) {riorPaso(unction ante}

f  }
 paso;
  entStep =   curr   
  ');dd('activeList.a.classep-${paso}`)Id(`stentByetElement.g       documtive');
 'acmove(lassList.re).ctStep}`ep-${curren`stmentById(Elecument.get
        do) {ctual()soAPaif (validarso) {
    Paso(paon siguienteunctiulario
fel formde pasos dn Gestió
// 
}
}     });
        }
     );
      aIA(iarConsult env          {
     ') er'Ent== f (e.key =          i
  e) { function(keypress',tener('addEventLisaChatInput.{
        iatInput) if (iaChput');
    'iaChatInementById(nt.getElcumet = doChatInpuconst iaIA
    put de chat  
    // In
    }
         });rPQRS();
  ea         crault();
   ventDef.pre      e   ) {
   function(eit', submer('enntListddEve.amPQRS     for
   S) {QR   if (formP);
 QRS'('formPyId.getElementBdocument= rmPQRS fonst  co  S
 de PQRo  Formulari   //
    
 
    });      }ita();
  endarC ag         );
  efault(ventDre   e.p       ion(e) {
  t', functbmir('suventListeneCita.addEvaNue   form) {
     aCita (formNuev if);
   ta'evaCiId('formNuByt.getElementcumenaCita = domNuevforst  conita
   a ce nuevormulario d// F  ms() {
  alizeFortin iniunctiolarios
fn de formutió// Ges
}
}

        break;    lizado
    está inicia   // Ya :
         dica' case 'ia-meak;
            bre
       ();oadPQRS         lpqrs':
     case '    eak;
    br
          scando se bucuao se carga El contenid  //       es':
    ientase 'pac
        cId) {switch(tab  bId) {
  ntent(taloadTabCoon uncti
}

f});   });
    
     Id);ent(tabntCoabdT loa        
   el tabcífico dntenido espe/ Cargar co       /         
  );
      dd('active'lassList.a${tabId}`).cab-(`tementByIdtElocument.ge       d);
     active'add('lassList.is.c    th      do
  onaleccir tab seiva // Act    
        
           '));vee('actit.removssLisent.clat => contontents.forEach(cabConten     t  );
     ctive')e('aList.remov btn.class=>h(btn tons.forEactabBut             activas
esascl Remover   //  
                b;
    et.taasat= this.dtabId    const      
    nction() {('click', fuistenerEventLutton.add{
        bton => Each(butons.forButttab  
     ent');
 ontab-c.tl('ctorAlquerySele document. =Contents   const tabbtn');
 rAll('.tab-Selecto.querycumenttons = dobBut const taabs() {
   ializeTinit
function  tabs Gestión de;

//hat();
})alizeIAC;
    initinda()geoadA();
    lzeFormsnitiali    ieTabs();
itializ() {
    inctionfun, tentLoaded'ner('DOMConentListe