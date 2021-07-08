<?php
// See Script at end, use the URL proxy if it is present.
// Perform authentication here to verify access based on user profile.
use ReallySimpleJWT\Token;
use Illuminate\Support\Facades\Http;
use App\Models\Studies\SharedStudies;
// Need the proxy 

$OrthancApiRoot = $_GET['proxy'];
$StudyInstanceUID = $_GET['study'];
$study = Http::withHeaders(['Authorization' => config('myconfigs.API_Authorization'),'Token' => config('myconfigs.API_Token')])
->withoutVerifying()
->post(env('APP_URL') . $OrthancApiRoot . '/tools/find',['Level' => 'Study', "Expand" => true, 'Query' => ['StudyInstanceUID' => $StudyInstanceUID]]);
$studies = $study->json();


function isSharedStudy($studies) {

    $study = SharedStudies::where("uuid", $studies['ID'])->where("shared_with", Auth::user()->doctor_id)->get();
    return (count($study) == 1)?true:false;
}

function VerifyStudyAccess($studies) {

    $access = false;
    $referrerid = explode(":", $studies[0]['MainDicomTags']['ReferringPhysicianName'])[0];
    if ($studies[0]['PatientMainDicomTags']['PatientID'] == Auth::user()->patientid || $referrerid == Auth::user()->doctor_id || count(array_intersect(json_decode(Auth::user()->user_roles), [3,4,5,6,7,8])) > 0 || isSharedStudy($studies[0])) $access = true;
    return $access;
}

/*
Array
(
    [0] => Array
        (
            [ID] => 27b02380-dfb9f47d-66ff2a89-4cb337ce-93f9789c
            [IsStable] => 1
            [LastUpdate] => 20210530T214955
            [MainDicomTags] => Array
                (
                    [AccessionNumber] => DEVACC00000067
                    [InstitutionName] => Cayman Medical Ltd.
                    [ReferringPhysicianName] => 0002:Talanow^Roland^^Dr.
                    [StudyDate] => 20210521
                    [StudyDescription] => MRI WRIST - RIGHT - WITHOUT CONTRAST
                    [StudyID] => 0
                    [StudyInstanceUID] => 1.3.6.1.4.1.56016.1.1.159.1621536265
                    [StudyTime] => 151235
                )

            [ParentPatient] => 29f8e73d-90261349-49bfb8d3-02c22bfb-ba09dd84
            [PatientMainDicomTags] => Array
                (
                    [PatientBirthDate] => 20210520
                    [PatientID] => DEV0000023
                    [PatientName] => Test^Patient 25
                    [PatientSex] => F
                )

            [Series] => Array
                (
                    [0] => 3c51d809-a27612d4-cdcd5988-ef32ea5e-ca2c8fd4

            [Type] => Study
        )

)
*/
// Verify the study and access.
if(count($studies) != 1 || !VerifyStudyAccess($studies)) {
    echo json_encode($studies);
    die('<h2 style = "color:white;text-align:center;width:max-content;margin:auto;">Study not on server or not unqiue, or not Authorized.</h2>');
}

$data = array (
'StudyInstanceUID' => $StudyInstanceUID
);


$payload = [

'iss' => 'Orthanc PACS',
'sub' => 'Viewer Token',
'iat' => time(),
'uid' => 1,
'exp' => time() + 60 * 5,
'data' => $data

];
$secret = 'Hello&MikeFooBar123';
$token = Token::customPayload($payload, $secret);
setcookie("JWTVIEWER", $token, [

    'expires' => time() + config('myconfigs.SESSION_RUNTIME'),
    'path' => config('myconfigs.COOKIE_PATH'),
    'domain' => config('myconfigs.COOKIE_DOMAIN'),
    'secure' => config('myconfigs.COOKIE_SECURE'),
    'httponly' => config('myconfigs.COOKIE_HTTP'),
    'samesite' => config('myconfigs.COOKIE_SAMESITE'),
]);

?>
<!doctype html>
<html class="wv-html">
  <head>
  <!-- base href for local hosting -->
  <base href="/stone/">
    <title>Stone Web Viewer</title>
    <meta charset="utf-8" />
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge, chrome=1" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <link rel="icon" href="data:;base64,iVBORw0KGgo=">
    
    <link rel="stylesheet" href="css/all.css">  <!-- Font Awesome -->
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="open-sans.css">
    <link rel="stylesheet" href="app.css">
    <link rel="stylesheet" href="app-fixes.css">
  </head>
  
  <body class="wv-body">
    <div id="wv">

      <div class="wvInfoScreen" v-show="modalNotDiagnostic" style="display: none">
        <div class="wvInfoPopup">
          <div class="wvInfoPopupLogo">
            <a href="https://www.orthanc-server.com/" target="_blank">
              <img style="width: 340px;" src="img/orthanc.png"/>
            </a>
          </div>
          <div class="wvInfoPopupText">
            <h3>Intended use</h3>
            <p>
              The Stone Web Viewer is intended for<br> patients
              reviewing their images,<br> for research and for quality
              assurance.
            </p>
            <h3>Versions</h3>
            <p>
              Stone Web viewer: {{ stoneWebViewerVersion }} <br/>
              Emscripten compiler: {{ emscriptenVersion }}
            </p>
          </div>
          <div class="wvInfoPopupForm">
            <br>
            <label>Show this information at startup
              <input type="checkbox" style="margin-left: 20px" v-model="settingNotDiagnostic">
            </label>
            <br><br>
            <div style="text-align: center;">
              <button class="wvInfoPopupCloseButton" @click="modalNotDiagnostic = false">
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <div class="wvInfoScreen" v-show="modalPreferences" style="display: none">
        <div class="wvInfoPopup">
          <div class="wvInfoPopupLogo">
            <a href="https://www.orthanc-server.com/" target="_blank">
              <img style="width: 340px;" src="img/orthanc.png"/>
            </a>
          </div>
          <div class="wvInfoPopupText">
            <h3>Intended use</h3>
            <p>
              The Stone Web Viewer is intended for<br> patients
              reviewing their images,<br> for research and for quality
              assurance.
            </p>
            <h3>Versions</h3>
            <p>
              Stone Web viewer: {{ stoneWebViewerVersion }} <br/>
              Emscripten compiler: {{ emscriptenVersion }}
            </p>
            <h3>User preferences</h3>
          </div>
          <div class="wvInfoPopupForm">
            <label>Warn about the intended use at startup
              <input type="checkbox" style="margin-left: 20px" v-model="settingNotDiagnostic">
            </label>
            <br>
            <label>Use software rendering (will reload the viewer)
              <input type="checkbox" style="margin-left: 20px" v-model="settingSoftwareRendering">
            </label>
            <br><br>
            <div style="text-align: center;">
              <button class="wvInfoPopupCloseButton" @click="ApplyPreferences()">
                Apply
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <div class="wvLoadingScreen" v-show="!ready && !modalNotDiagnostic && !modalPreferences" style="display: none">
        <span class="wvLoadingSpinner">
          <div class="bounce1"></div>
          <div class="bounce2"></div>
          <div class="bounce3"></div>
        </span>
      </div>

      <div class="fluid-height fluid-width" v-show="ready && !modalNotDiagnostic && !modalPreferences"
           style="display: none">

        <div class="wvWarning wvPrintExclude" v-show="modalWarning">
          <div class="wvWarning-content clearfix">
            <span class="wvWarning-text">
              <h2 class="mb10"><i class="fa fa-exclamation-triangle wvWarning-icon mr5"></i>Warning!</h2>
              <p class="mn mb10" style="color:#000">
                You browser is not supported. You might expect
                inconsistent behaviours and must not use the viewer to
                produce a diagnostic.
              </p>
            </span> 
          </div>
          <div class="text-right mb10 mr10">
            <button class="btn btn-primary" @click="modalWarning=false">OK</button>
          </div>
        </div>


        <div class="wvLayoutLeft wvLayoutLeft--closed" v-show="!leftVisible">
          <div class="wvLayoutLeft__actions--outside" style="z-index:10">
            <button class="wvLayoutLeft__action button__base wh__25 lh__25 text-center"
                    @click="leftVisible = true">
              <i class="fa fa-angle-double-right"></i>
            </button>
          </div>
        </div>


        <div class="wvLayoutLeft wvPrintExclude" v-show="leftVisible"
             v-bind:class="{ 'wvLayoutLeft--small': leftMode == 'small' }" 
             >
          <div class="wvLayoutLeft__actions" style="z-index:10">
            <button class="wvLayoutLeft__action button__base wh__25 lh__25 text-center"
                    @click="leftVisible = false">
              <i class="fa fa-angle-double-left"></i>
            </button>
          </div>
          <div class="wvLayoutLeft__content">
            <div class="wvLayoutLeft__contentTop">
              <div class="float__left dropdown" style="max-width: calc(100% - 4.5rem); height:4.5rem !important" v-show="leftMode != 'small'">
                <button type="button" class="wvButton--border" data-toggle="dropdown">
                  {{ getSelectedStudies }}
                  <span class="caret"></span>
                </button>
                <ul class="dropdown-menu checkbox-menu allow-focus">
                  <li v-for="study in studies"
                      v-bind:class="{ active: study.selected }" 
                      @click="study.selected = !study.selected">
                    <a>
                      {{ study.tags[STUDY_DESCRIPTION] }}
                      <small v-if="study.tags[STUDY_DATE].length > 0">
                        [{{ FormatDate(study.tags[STUDY_DATE]) }}]
                      </small>
                      <span v-if="study.selected">&nbsp;<i class="fa fa-check"></i></span>
                    </a> 
                  </li>
                </ul>
              </div>

              <div class="float__right wvButton" v-if="leftMode == 'grid'" @click="leftMode = 'full'">
                <i class="fa fa-th-list"></i>
              </div>
              <div class="float__right wvButton" v-if="leftMode == 'full'" @click="leftMode = 'small'">
                <i class="fa fa-ellipsis-v"></i>
              </div>
              <div class="float__right wvButton" v-if="leftMode == 'small'" @click="leftMode = 'grid'">
                <i class="fa fa-th"></i>
              </div>

              <!--p class="clear disclaimer mbn">For patients, teachers and researchers.</p-->
            </div>        
            <div class="wvLayoutLeft__contentMiddle">

              <div v-for="study in studies">
                <div v-if="study.selected">
                  <div v-bind:class="'wvStudyIsland--' + study.color">
                    <div v-bind:class="'wvStudyIsland__header--' + study.color">
                      <!-- Actions -->
                      <div class="wvStudyIsland__actions"
                           v-bind:class="{ 'wvStudyIsland__actions--oneCol': leftMode == 'small' }">
                        <a class="wvButton"
                           v-show="globalConfiguration.DownloadStudyEnabled && 'OrthancApiRoot' in globalConfiguration">
                          <!-- download --> 
                          <i class="fa fa-download" v-show="!creatingArchive"
                             data-toggle="tooltip" data-title="Download the study"
                             @click="DownloadStudy(study.tags[STUDY_INSTANCE_UID])"></i>
                          <i class="fas fa-sync fa-spin" v-show="creatingArchive"
                             data-toggle="tooltip" data-title="A ZIP archive is being created by Orthanc..."></i>
                        </a>
                      </div>
                      
                      <!-- Title -->
                      {{ study.tags[STUDY_DESCRIPTION] }}
                      <br/>
                      <small>{{ FormatDate(study.tags[STUDY_DATE]) }}</small>
                    </div>

                    <div class="wvStudyIsland__main">
                      <ul class="wvSerieslist">

                        <!-- Series without multiple multiframe instances -->
                        <span v-for="seriesIndex in study.series">
                          <li class="wvSerieslist__seriesItem"
                              v-bind:class="{ highlighted : GetActiveSeries().includes(series[seriesIndex].tags[SERIES_INSTANCE_UID]), 'wvSerieslist__seriesItem--list' : leftMode != 'grid', 'wvSerieslist__seriesItem--grid' : leftMode == 'grid' }"
                              v-on:dragstart="SeriesDragStart($event, seriesIndex)"
                              v-on:click="ClickSeries(seriesIndex)"
                              v-if="series[seriesIndex].multiframeInstances === null">
                            <div class="wvSerieslist__picture" style="z-index:0"
                                 draggable="true"
                                 v-if="series[seriesIndex].type != stone.ThumbnailType.UNKNOWN"
                                 >
                              <div v-if="series[seriesIndex].type == stone.ThumbnailType.LOADING">
                                <img src="img/loading.gif"
                                     style="vertical-align:baseline"
                                     width="65px" height="65px"
                                     />
                              </div>

                              <i v-if="series[seriesIndex].type == stone.ThumbnailType.PDF"
                                 class="wvSerieslist__placeholderIcon fa fa-file-pdf"
                                 v-bind:title="leftMode == 'full' ? null : series[seriesIndex].tags[SERIES_DESCRIPTION]"></i>

                              <i v-if="series[seriesIndex].type == stone.ThumbnailType.VIDEO"
                                 class="wvSerieslist__placeholderIcon fa fa-video"
                                 v-bind:title="leftMode == 'full' ? null : series[seriesIndex].tags[SERIES_DESCRIPTION]"></i>
                              
                              <div v-if="[stone.ThumbnailType.IMAGE, stone.ThumbnailType.NO_PREVIEW].includes(series[seriesIndex].type)"
                                   class="wvSerieslist__placeholderIcon"
                                   v-bind:title="leftMode == 'full' ? null : '[' + series[seriesIndex].tags[MODALITY] + '] ' + series[seriesIndex].tags[SERIES_DESCRIPTION]">
                                <i v-if="series[seriesIndex].type == stone.ThumbnailType.NO_PREVIEW"
                                   class="fa fa-eye-slash"></i>

                                <img v-if="series[seriesIndex].type == stone.ThumbnailType.IMAGE"
                                     v-bind:src="series[seriesIndex].thumbnail"
                                     style="vertical-align:baseline"
                                     width="65px" height="65px"
                                     v-bind:title="leftMode == 'full' ? null : '[' + series[seriesIndex].tags[MODALITY] + '] ' + series[seriesIndex].tags[SERIES_DESCRIPTION]"
                                     />
                                
                                <div v-bind:class="'wvSerieslist__badge--' + study.color"
                                     v-if="series[seriesIndex].numberOfFrames != 0">{{ series[seriesIndex].numberOfFrames }}</div>
                              </div>
                            </div>

                            <div v-if="leftMode == 'full'" class="wvSerieslist__information"
                                 draggable="true"
                                 v-on:dragstart="SeriesDragStart($event, seriesIndex)"
                                 v-on:click="ClickSeries(seriesIndex)">
                              <p class="wvSerieslist__label">
                                [{{ series[seriesIndex].tags[MODALITY] }}]
                                {{ series[seriesIndex].tags[SERIES_DESCRIPTION] }}
                              </p>
                            </div>
                          </li>


                          <!-- Series with multiple multiframe instances (CINE) -->
                          <li class="wvSerieslist__seriesItem"
                              v-bind:class="{ highlighted : GetActiveMultiframeInstances().includes(sopInstanceUid), 'wvSerieslist__seriesItem--list' : leftMode != 'grid', 'wvSerieslist__seriesItem--grid' : leftMode == 'grid' }"
                              v-for="(numberOfFrames, sopInstanceUid) in series[seriesIndex].multiframeInstances"
                              v-on:dragstart="MultiframeInstanceDragStart($event, seriesIndex, sopInstanceUid)"
                              v-on:click="ClickMultiframeInstance(seriesIndex, sopInstanceUid)">
                            <div class="wvSerieslist__picture" style="z-index:0"
                                 draggable="true">
                              <img v-if="series[seriesIndex].type == stone.ThumbnailType.IMAGE"
                                   v-bind:src="sopInstanceUid in multiframeInstanceThumbnails ? multiframeInstanceThumbnails[sopInstanceUid] : series[seriesIndex].thumbnail"
                                   style="vertical-align:baseline"
                                   width="65px" height="65px"
                                   v-bind:title="leftMode == 'full' ? null : '[' + series[seriesIndex].tags[MODALITY] + '] ' + series[seriesIndex].tags[SERIES_DESCRIPTION]"
                                   />
                              
                              <div v-bind:class="'wvSerieslist__badge--' + study.color">
                                {{ numberOfFrames }}
                              </div>
                            </div>

                            <div v-if="leftMode == 'full'" class="wvSerieslist__information"
                                 draggable="true"
                                 v-on:dragstart="MultiframeInstanceDragStart($event, seriesIndex, sopInstanceUid)"
                                 v-on:click="MultiframeInstanceDragStart($event, seriesIndex, sopInstanceUid)">
                              <p class="wvSerieslist__label">
                                [{{ series[seriesIndex].tags[MODALITY] }}]
                                {{ series[seriesIndex].tags[SERIES_DESCRIPTION] }}
                              </p>
                            </div>
                          </li>
                          
                        </span>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

            </div>        
            <div class="wvLayoutLeft__contentBottom">
            </div>        
          </div>
        </div>
        <div class="wvLayout__main"
             v-bind:class="{ 'wvLayout__main--smallleftpadding': leftVisible && leftMode == 'small', 'wvLayout__main--leftpadding': leftVisible && leftMode != 'small' }" 
             >

          <div class="wvToolbar wvToolbar--top wvPrintExclude" style="left: 0px; text-align:left">
            <a href="https://www.orthanc-server.com/" target="_blank">
              <img src="img/orthanc.png" style="max-height: 100%; padding: 8px" />
            </a>
          </div>
            
          <div class="wvToolbar wvToolbar--top wvPrintExclude">
            <div class="ng-scope inline-object">
              <div class="tbGroup">
                <div class="tbGroup__toggl">
                  <button class="wvButton"
                          v-bind:class="{ 'wvButton--underline' : !viewportLayoutButtonsVisible }"
                          data-toggle="tooltip" data-title="Change layout"
                          @click="viewportLayoutButtonsVisible = !viewportLayoutButtonsVisible;HideAllTooltips()">
                    <i class="fa fa-th"></i>
                  </button>
                </div>
                
                <div class="tbGroup__buttons--bottom" v-show="viewportLayoutButtonsVisible">
                  <div class="inline-object">
                    <button class="wvButton" @click="SetViewportLayout('1x1')">
                      <img src="img/grid1x1.png" style="width:1em;height:1em" />
                    </button>
                  </div>
                  <div class="inline-object">
                    <button class="wvButton" @click="SetViewportLayout('2x1')">
                      <img src="img/grid2x1.png" style="width:1em;height:1em" />
                    </button>
                  </div>
                  <div class="inline-object">
                    <button class="wvButton" @click="SetViewportLayout('1x2')">
                      <img src="img/grid1x2.png" style="width:1em;height:1em" />
                    </button>
                  </div>
                  <div class="inline-object">
                    <button class="wvButton" @click="SetViewportLayout('2x2')">
                      <img src="img/grid2x2.png" style="width:1em;height:1em" />
                    </button>
                  </div>
                </div>
              </div>
            </div>


            <div class="ng-scope inline-object">
              <div class="tbGroup">
                <div class="tbGroup__toggl">
                  <button class="wvButton"
                          v-bind:class="{ 'wvButton--underline' : !mouseActionsVisible }"
                          data-toggle="tooltip" data-title="Mouse actions"
                          @click="mouseActionsVisible = !mouseActionsVisible;HideAllTooltips()">
                    <i class="fa fa-mouse-pointer"></i>
                  </button>
                </div>
                
                <div class="tbGroup__buttons--bottom" v-show="mouseActionsVisible">
                  <div class="inline-object" v-if="globalConfiguration.CombinedToolEnabled">
                    <button class="wvButton--underline"
                            data-toggle="tooltip" data-title="Combined tool"
                            v-bind:class="{ 'active' : mouseTool == MOUSE_TOOL_COMBINED }"
                            @click="SetCombinedToolActions()">
                      <i class="far fa-hand-point-up"></i>
                    </button>
                  </div>
                  <div class="inline-object">
                    <button class="wvButton--underline"
                            data-toggle="tooltip" data-title="Zoom"
                            v-bind:class="{ 'active' : mouseTool == MOUSE_TOOL_ZOOM }"
                            @click="SetMouseButtonActions(MOUSE_TOOL_ZOOM, stone.WebViewerAction.ZOOM, stone.WebViewerAction.ZOOM, stone.WebViewerAction.ZOOM)">
                      <i class="fas fa-search"></i>
                    </button>
                  </div>
                  <div class="inline-object">
                    <button class="wvButton--underline"
                            data-toggle="tooltip" data-title="Pan"
                            v-bind:class="{ 'active' : mouseTool == MOUSE_TOOL_PAN }"
                            @click="SetMouseButtonActions(MOUSE_TOOL_PAN, stone.WebViewerAction.PAN, stone.WebViewerAction.PAN, stone.WebViewerAction.PAN)">
                      <i class="fas fa-arrows-alt"></i>
                    </button>
                  </div>
                  <div class="inline-object">
                    <button class="wvButton--underline"
                            data-toggle="tooltip" data-title="3D cross-hair"
                            v-bind:class="{ 'active' : mouseTool == MOUSE_TOOL_CROSSHAIR }"
                            @click="SetMouseButtonActions(MOUSE_TOOL_CROSSHAIR, stone.WebViewerAction.CROSSHAIR, stone.WebViewerAction.PAN, stone.WebViewerAction.ZOOM)">
                      <i class="fas fa-crosshairs"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>



            <!--div class="ng-scope inline-object">
              <button class="wvButton--underline text-center active">
                <i class="fa fa-hand-pointer-o"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center">
                <i class="fa fa-search"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center">
                <i class="fa fa-arrows"></i>
              </button>
            </div-->

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="Invert contrast"
                      v-on:click="InvertContrast()">
                <i class="fa fa-adjust"></i>
              </button>
            </div>
            
            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="Change windowing"
                      id="windowing-popover"
                      v-bind:class="{ 'active' : showWindowing }"
                      v-on:click="ToggleWindowing()">
                <i class="fa fa-sun"></i>
              </button>
            </div>

            <div id="windowing-content" v-show="showWindowing"
                 class="popover wvToolbar__windowingPresetConfigPopover"
                 style="position: absolute; display: block"
                 >
              <div class="arrow"></div>
              <h3 class="popover-title">Change windowing</h3>
              <div class="popover-content">
                
<!-- 
                <p class="wvToolbar__windowingPresetConfigNotice">
                    Click on the button to toggle the windowing tool or apply a preset to the selected viewport.
                    SDS added the below, otherwise sends a request.
                </p>
 -->
                <ul class="wvToolbar__windowingPresetList">
                  <li v-for="preset in windowingPresets"
                      class="wvToolbar__windowingPresetListItem">
                    <a href="#" v-on:click.prevent="SetWindowing(preset.center, preset.width)">
                      {{ preset.name }} <small>({{ preset.info }})</small>
                    </a>
                  </li>
                  <li v-for="preset in globalConfiguration.WindowingPresets"
                      class="wvToolbar__windowingPresetListItem">
                    <a href="#" v-on:click.prevent="SetWindowing(preset.WindowCenter, preset.WindowWidth)">
                      {{ preset.Name }}
                      <small>
                        (C {{preset.WindowCenter}}, W {{preset.WindowWidth}})
                      </small>
                    </a>
                  </li>
                </ul>
              </div>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="Flip horizontally"
                      v-on:click="FlipX()">
                <i class="fas fa-exchange-alt"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="Flip vertically"
                      v-on:click="FlipY()">
                <i class="fas fa-exchange-alt fa-rotate-90"></i>
              </button>
            </div>
            
            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      v-bind:class="{ 'active' : mouseTool == MOUSE_TOOL_CREATE_SEGMENT }"
                      v-on:click="SetLeftMouseButtonAction(MOUSE_TOOL_CREATE_SEGMENT, stone.WebViewerAction.CREATE_SEGMENT)"
                      data-toggle="tooltip" data-title="Measure length">
                <i class="fas fa-arrows-alt-h"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      v-bind:class="{ 'active' : mouseTool == MOUSE_TOOL_CREATE_ANGLE }"
                      v-on:click="SetLeftMouseButtonAction(MOUSE_TOOL_CREATE_ANGLE, stone.WebViewerAction.CREATE_ANGLE)"
                      data-toggle="tooltip" data-title="Measure angle">
                <i class="fas fa-angle-left fa-lg"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      v-bind:class="{ 'active' : mouseTool == MOUSE_TOOL_CREATE_CIRCLE }"
                      v-on:click="SetLeftMouseButtonAction(MOUSE_TOOL_CREATE_CIRCLE, stone.WebViewerAction.CREATE_CIRCLE)"
                      data-toggle="tooltip" data-title="Measure circle">
                <i class="far fa-circle"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      v-bind:class="{ 'active' : mouseTool == MOUSE_TOOL_REMOVE_MEASURE }"
                      v-on:click="SetLeftMouseButtonAction(MOUSE_TOOL_REMOVE_MEASURE, stone.WebViewerAction.REMOVE_MEASURE)"
                      data-toggle="tooltip" data-title="Delete measurement">
                <i class="fas fa-trash"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="Synchronized browsing"
                      v-bind:class="{ 'active' : synchronizedBrowsing }"
                      v-on:click="synchronizedBrowsing = !synchronizedBrowsing">
                <i class="fa fa-link"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="Reference lines"
                      v-bind:class="{ 'active' : showReferenceLines }"
                      v-on:click="showReferenceLines = !showReferenceLines">
                <i class="fa fa-bars"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="Show image information"
                      v-bind:class="{ 'active' : showInfo }"
                      v-on:click="showInfo = !showInfo">
                <i class="fa fa-info-circle"></i>
              </button>
            </div>


            <div class="ng-scope inline-object" v-if="globalConfiguration.PrintEnabled">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="Print"
                      onclick="window.print()">
                <i class="fas fa-print"></i>
              </button>
            </div>

            <div class="ng-scope inline-object" v-if="globalConfiguration.DownloadAsJpegEnabled">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="Download as JPEG"
                      v-on:click="DownloadJpeg()">
                <i class="fas fa-file-download"></i>
              </button>
            </div>

            <div class="ng-scope inline-object">
              <button class="wvButton--underline text-center"
                      data-toggle="tooltip" data-title="User preferences"
                      v-on:click="modalPreferences = true">
                <i class="fa fa-user"></i>
              </button>
            </div>
          </div>

          
          <div class="wvLayout__splitpane--toolbarAtTop wvPrintFullPage">
            <div id="viewport" class="wvSplitpane">
              <viewport v-on:updated-series="SetViewportSeries(1, $event)"
                        v-on:selected-viewport="activeViewport=1"
                        v-show="viewport1Visible"
                        canvas-id="canvas1"
                        v-bind:content="viewport1Content"
                        v-bind:left="viewport1Left"
                        v-bind:top="viewport1Top"
                        v-bind:width="viewport1Width"
                        v-bind:height="viewport1Height"
                        v-bind:show-info="showInfo"
                        v-bind:global-configuration="globalConfiguration"
                        v-bind:active="activeViewport==1"></viewport>
              <viewport v-on:updated-series="SetViewportSeries(2, $event)"
                        v-on:selected-viewport="activeViewport=2"
                        v-show="viewport2Visible"
                        canvas-id="canvas2"
                        v-bind:content="viewport2Content"
                        v-bind:left="viewport2Left"
                        v-bind:top="viewport2Top"
                        v-bind:width="viewport2Width"
                        v-bind:height="viewport2Height"
                        v-bind:show-info="showInfo"
                        v-bind:global-configuration="globalConfiguration"
                        v-bind:active="activeViewport==2"></viewport>
              <viewport v-on:updated-series="SetViewportSeries(3, $event)"
                        v-on:selected-viewport="activeViewport=3"
                        v-show="viewport3Visible"
                        canvas-id="canvas3"
                        v-bind:content="viewport3Content"
                        v-bind:left="viewport3Left"
                        v-bind:top="viewport3Top"
                        v-bind:width="viewport3Width"
                        v-bind:height="viewport3Height"
                        v-bind:show-info="showInfo"
                        v-bind:global-configuration="globalConfiguration"
                        v-bind:active="activeViewport==3"></viewport>
              <viewport v-on:updated-series="SetViewportSeries(4, $event)"
                        v-on:selected-viewport="activeViewport=4"
                        v-show="viewport4Visible"
                        canvas-id="canvas4"
                        v-bind:content="viewport4Content"
                        v-bind:left="viewport4Left"
                        v-bind:top="viewport4Top"
                        v-bind:width="viewport4Width"
                        v-bind:height="viewport4Height"
                        v-bind:show-info="showInfo"
                        v-bind:global-configuration="globalConfiguration"
                        v-bind:active="activeViewport==4"></viewport>
            </div>
          </div>
        </div>
      </div>      
    </div>

    
    <script type="text/x-template" id="viewport-template">
      <div v-bind:id="canvasId + '-container'"
           v-bind:style="{ padding:'2px', 
                         position:'absolute', 
                         left: left, 
                         top: top,
                         width: width, 
                         height: height }">
        <div v-bind:class="{ 'wvSplitpane__cellBorder--selected' : active, 
                           'wvSplitpane__cellBorder' : content.series.color == '', 
                           'wvSplitpane__cellBorder--blue' : content.series.color == 'blue', 
                           'wvSplitpane__cellBorder--red' : content.series.color == 'red',
                           'wvSplitpane__cellBorder--green' : content.series.color == 'green', 
                           'wvSplitpane__cellBorder--yellow' : content.series.color == 'yellow', 
                           'wvSplitpane__cellBorder--violet' : content.series.color == 'violet'
                           }" 
             ondragover="event.preventDefault()"
             v-on:drop="DragDrop($event)"
             style="width:100%;height:100%">
          <div class="wvSplitpane__cell" 
               v-on:click="MakeActive()">
            <div v-show="status == 'ready'"
                 style="position:absolute; left:0; top:0; width:100%; height:100%;">
              <!--div style="width: 100%; height: 100%; background-color: red"></div-->
              <canvas v-bind:id="canvasId" class="viewport-canvas"
                      style="position:absolute; left:0; top:0; width:100%; height:100%"
                      oncontextmenu="return false"></canvas>

              <div v-show="showInfo">
                <div class="wv-overlay">
                  <div v-if="'tags' in content.series" class="wv-overlay-topleft">
                    {{ content.series.tags[PATIENT_NAME] }}<br/>
                    {{ content.series.tags[PATIENT_ID] }}<br/>
                    {{ content.series.tags[PATIENT_SEX] }}<br/>
                    {{ app.FormatDate(content.series.tags[PATIENT_BIRTH_DATE]) }}<br/>
                  </div>
                  <div v-if="'tags' in content.series" class="wv-overlay-topright">
                    {{ content.series.tags[ACCESSSION_NUMBER] }}<br/>
                    {{ content.series.tags[STUDY_DESCRIPTION] }}<br/>
                    {{ app.FormatDate(content.series.tags[STUDY_DATE]) }}<br/>
                    {{ content.series.tags[SERIES_NUMBER] }} | {{ content.series.tags[SERIES_DESCRIPTION] }}
                  </div>
                  <div class="wv-overlay-bottomleft wvPrintExclude" style="bottom: 0px">
                    <div v-show="instanceNumber != 0" style="padding-bottom: 1em">
                      Instance number: {{ instanceNumber }}
                    </div>
                    <span v-show="numberOfFrames != 0">
                      <div style="width: 12em;" v-show="cineControls">
                        <label>
                          Frame rate
                          <span class="wv-play-button-config-framerate-wrapper">
                            <input type="range" min="1" max="60" v-model="cineFramesPerSecond"
                                   class="wv-play-button-config-framerate">
                          </span>
                          {{ cineFramesPerSecond }} fps
                        </label>
                      </div>
                      <div class="btn-group btn-group-sm" role="group">                        
                        <button class="btn btn-primary" @click="stone.GoToFirstFrame(canvasId)">
                          <i class="fas fa-fast-backward"></i>
                        </button>
                        <button class="btn btn-primary" @click="DecrementFrame()">
                          <i class="fas fa-step-backward"></i>
                        </button>
                      </div>
                      <span data-toggle="tooltip" data-title="Current frame number">
                        &nbsp;&nbsp;{{ currentFrame }} / {{ numberOfFrames }}&nbsp;&nbsp;
                      </span>
                      <div class="btn-group btn-group-sm" role="group">                        
                        <button class="btn btn-primary" @click="IncrementFrame()">
                          <i class="fas fa-step-forward"></i>
                        </button>
                        <button class="btn btn-primary" @click="stone.GoToLastFrame(canvasId)">
                          <i class="fas fa-fast-forward"></i>
                        </button>
                      </div>
                      <div class="btn-group btn-group-sm" role="group">                        
                        <button type="button" class="btn btn-primary" @click="CinePlay()">
                          <i class="fas fa-play fa-flip-horizontal"></i>
                        </button>
                        <button type="button" class="btn btn-primary" @click="CinePause()">
                          <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" class="btn btn-primary" @click="CineBackward()">
                          <i class="fas fa-play"></i>
                        </button>
                      </div>
                    </span>
                  </div>
                  <div class="wv-overlay-bottomright wvPrintExclude" style="bottom: 0px">
                    <div v-if="windowingWidth != 0">
                      ww/cc: {{ windowingCenter }} / {{ windowingWidth }}
                    </div>
                    <div style="padding-top: 0.5em">
                      <div v-show="quality == stone.DisplayedFrameQuality.NONE"
                           style="display:inline-block;background-color:red;width:1em;height:1em" />
                      <div v-show="quality == stone.DisplayedFrameQuality.LOW"
                           style="display:inline-block;background-color:orange;width:1em;height:1em" />
                      <div v-show="quality == stone.DisplayedFrameQuality.HIGH"
                           style="display:inline-block;background-color:green;width:1em;height:1em" />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="status == 'waiting'" class="wvPaneOverlay">
              [ drop a series here ]
            </div>

            <!-- Don't use "v-if" here, otherwise the tooltips of the PDF viewer are not initialized -->
            <div v-show="status == 'pdf'" >
              <pdf-viewer v-bind:prefix="canvasId + '-pdf'" ref="pdfViewer"></pdf-viewer>
            </div>
                
            <div v-if="status == 'video'" class="wvPaneOverlay">
              <div v-if="!('OrthancApiRoot' in globalConfiguration) || videoUri.length == 0">
                [ cannot play videos using DICOMweb yet ]
              </div>
              <div v-if="'OrthancApiRoot' in globalConfiguration && videoUri.length > 0">
                <video class="wvVideo" autoplay="" loop="" controls="" preload="auto" type="video/mp4"
                       v-bind:src="videoUri">
                </video>
              </div>
            </div>
                
            <div v-if="status == 'loading'" class="wvPaneOverlay">
              <span class="wvLoadingSpinner">
                <div class="bounce1"></div>
                <div class="bounce2"></div>
                <div class="bounce3"></div>
              </span>
            </div>
          </div>
        </div>
      </div>
    </script>


    <script type="text/x-template" id="pdf-viewer">
      <div style="position:absolute; left:0; top:0; width:100%; height:100%;">
        <!-- "line-height: 0px" to fit height: https://stackoverflow.com/a/12616341/881731 -->
        <div v-bind:id="prefix + '-container'"
             style="position: absolute; left: 0; top: 0; width:100%;height:100%;overflow:auto;line-height: 0px;">
          <canvas v-bind:id="prefix + '-canvas'"
                  style="position: absolute; top:0px; left:0px;"
                  oncontextmenu="return false"></canvas>
        </div>

        <div class="wv-overlay">
          <div class="wv-overlay-bottomleft wvPrintExclude">
            <button class="btn btn-primary" @click="FitWidth()"
                    data-toggle="tooltip" data-title="Fit page width">
              <i class="fas fa-text-width"></i>
            </button>
            <button class="btn btn-primary" @click="FitHeight()"
                    data-toggle="tooltip" data-title="Fit page height">
              <i class="fas fa-text-height"></i>
            </button>
            <button class="btn btn-primary" @click="ZoomIn()"
                    data-toggle="tooltip" data-title="Zoom in">
              <i class="fas fa-search-plus"></i>
            </button>
            <button class="btn btn-primary" @click="ZoomOut()"
                    data-toggle="tooltip" data-title="Zoom out">
              <i class="fas fa-search-minus"></i>
            </button>
            <button class="btn btn-primary" @click="PreviousPage()"
                    data-toggle="tooltip" data-title="Show previous page">
              <i class="fa fa-chevron-circle-left"></i>
            </button>
            &nbsp;&nbsp;{{currentPage}} / {{countPages}}&nbsp;&nbsp;
            <button class="btn btn-primary" @click="NextPage()"
                    data-toggle="tooltip" data-title="Show next page">
              <i class="fa fa-chevron-circle-right"></i>
            </button>
          </div>
        </div>
      </div>
    </script>

    

    <script src="js/jquery-3.4.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/vue.min.js"></script>
    <script src="js/axios.min.js"></script>
    <script src="js/pdf.js"></script>
    
    <script src="stone.js"></script>
    <script src="pdf-viewer.js"></script> 
    
    <!-- Must be before inclusion of "app.js" -->
    <!-- Thing below dynamically adds an Auth Header to AJAX requests (axios) to the PACS server, etc -->
    
    <script>
        
    	synchronizedBrowsing = true;
        XMLHttpRequest.prototype.origOpen = XMLHttpRequest.prototype.open;
    	XMLHttpRequest.prototype.open   = function (method,url) {
    	
        console.log(app.globalConfiguration.DicomWebRoot);
        console.log(url);

        if (url.startsWith(app.globalConfiguration.DicomWebRoot)) {
        this.origOpen.apply(this, arguments);
        this.setRequestHeader('Authorization', 'JWT ' + 'test');
        }
        else {
        	this.origOpen.apply(this, arguments);
        	this.setRequestHeader('Authorization', 'JWT ' + 'test');
        }
    
    };
    </script>
    
    <script src="app.js"></script>
    
    <!-- Load the config and WASM here rather than in app.js -->
    
    <script>
    
    $(document).ready(function() {
    
        RefreshTooltips();

        //app.modalWarning = true;

        app.globalConfiguration = ParseJsonWithComments({
        
            "DateFormat" : "DD/MM/YYYY",
            "WindowingPresets" : [
              {"Name" : "CT Lung",    "WindowCenter" : -400, "WindowWidth" : 1600},
              {"Name" : "CT Abdomen", "WindowCenter" : 60,   "WindowWidth" : 400},
              {"Name" : "CT Bone",    "WindowCenter" : 300,  "WindowWidth" : 1500},
              {"Name" : "CT Brain",   "WindowCenter" : 40,   "WindowWidth" : 80},
              {"Name" : "CT Chest",   "WindowCenter" : 40,   "WindowWidth" : 400},
              {"Name" : "CT Angio",   "WindowCenter" : 300,  "WindowWidth" : 600}
            ],
            "CombinedToolEnabled" : true,
            "CombinedToolBehaviour" : {
              "LeftMouseButton" : "Windowing",
              "MiddleMouseButton" : "Pan",
              "RightMouseButton" : "Zoom"
            },
            "PrintEnabled" : true,
            "DownloadAsJpegEnabled" : true,
            "DownloadStudyEnabled" : true,
            "ExpectedMessageOrigin" : "<?php echo  $OrthancApiRoot ?>" ,
            "DicomWebRoot" : "<?php echo  $OrthancApiRoot . '/dicom-web' ?>",
            "DicomCacheSize" : 128,
            "OrthancApiRoot" : "<?php echo  $OrthancApiRoot ?>"
            }
      
      );

      axios.get(WASM_SOURCE)
        .then(function (response) {
          var script = document.createElement('script');
          script.innerHTML = response.data;
          script.type = 'text/javascript';
          document.body.appendChild(script);
        })
        .catch(function (error) {
          alert('Cannot load the WebAssembly framework');
        });

});
    </script>
    <script src="print.js"></script>
  </body>
</html>
<?php /**PATH /nginx-home/laravel/resources/views/stone_viewer.blade.php ENDPATH**/ ?>