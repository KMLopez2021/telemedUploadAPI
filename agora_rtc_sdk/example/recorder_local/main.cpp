#include <atomic>
#include <csignal>
#include <sys/stat.h>
#include <sys/time.h>
#include <sstream>
#include <unistd.h>

#include "opt_parser.h"
#include "config.h"
#include "AgoraBase.h"
#include "IAgoraService.h"
#include "IAgoraMediaComponentFactory.h"
#include "IAgoraMediaRtcRecorder.h"
#include "recorderEventHandler.h"
#include "time_util.h"
#include "log.h"


std::atomic<bool> g_bSignalStop(false);

void signal_handler(int signo) {
  (void)signo;
  g_bSignalStop = true;
}

void set_record_folder(std::string& recording_folder, std::string& cname) {
  
  if (recording_folder.size() > 0 && recording_folder[recording_folder.size() - 1] != '/'){
    recording_folder += '/';
  }
  
  // get date
  time_t rawtime;
  struct tm * timeinfo;
  char timebuffer[20];
  time(&rawtime);
  timeinfo = gmtime(&rawtime);
  strftime(timebuffer, 20, "%Y%m%d/", timeinfo);
  recording_folder += std::string(timebuffer);
  //construct data recording_folder
  struct stat st;
  if (stat(recording_folder.c_str(), &st) == -1) {
    mkdir(recording_folder.c_str(), 0777);
  }
  //construct channel cname and time recording_folder
  recording_folder += cname;
  recording_folder += "_";
  Time2UTCStrWithSlash_ns(timebuffer, 20);
  recording_folder += std::string(timebuffer);
  if (stat(recording_folder.c_str(), &st) == -1) {
    mkdir(recording_folder.c_str(), 0777);
  }
}

void init_subscribe_uids(recorder_config& config) {
  if (!config.subscribeVideoUids.empty()) {
    std::istringstream ss(config.subscribeVideoUids);
    std::string uid;
    while (std::getline(ss, uid, ',')) {
      config.subscribeVideoUserList.insert(uid);
    }
  }
  
  if (!config.subscribeAudioUids.empty()) {
    std::istringstream ss(config.subscribeAudioUids);
    std::string uid;
    while (std::getline(ss, uid, ',')) {
      config.subscribeAudioUserList.insert(uid);
    }
  }
}

int main(int argc, char * const argv[]) 
{
  std::signal(SIGQUIT, signal_handler);
  std::signal(SIGABRT, signal_handler);
  std::signal(SIGINT, signal_handler);

  recorder_config config;
  agora::base::opt_parser parser;
  parser.add_long_opt("appId", &config.appId, "App Id/must", agora::base::opt_parser::require_argu);
  parser.add_long_opt("channel", &config.ChannelName, "Channel Id/must", agora::base::opt_parser::require_argu);
  parser.add_long_opt("uid", &config.UserId, "User Id default is 0/option");
  parser.add_long_opt("channelKey", &config.token, "channelKey/option");
  parser.add_long_opt("isMixingEnabled", &config.isMix, "Mixing Enable? (0:1)/option");
  parser.add_long_opt("idle", &config.idleLimitSec, "Default 300s, should be above 3s/option");
  parser.add_long_opt("recordFileRootDir", &config.recordFileRootDir, "recording file root dir/option");
  parser.add_long_opt("autoSubscribe", &config.autoSubscribe, "Auto subscribe video/audio streams of each uid. (0: false 1: true, default 1 /option)");
  parser.add_long_opt("useStringUid", &config.useStringUid, "use string uid, defaut: false");
  parser.add_long_opt("subscribeVideoRegula", &config.subscribeVideoRegula, "subscribe video regula, default is empty, means subscribe all video streams");
  parser.add_long_opt("subscribeAudioRegula", &config.subscribeAudioRegula, "subscribe audio regula, default is empty, means subscribe all audio streams");
  parser.add_long_opt("isAudioOnly", &config.isAudioOnly, "Default 0:A/V, 1:AudioOnly (0:1)/option");
  parser.add_long_opt("isVideoOnly", &config.isVideoOnly, "Default 0:A/V, 1:VideoOnly (0:1)/option");
  parser.add_long_opt("decryptionMode", &config.decryptionMode, "decryption Mode, default is NULL/option, available modes: AES_128_XTS, AES_128_ECB, AES_256_XTS, SM4_128_ECB, AES_128_GCM, AES_256_GCM, AES_128_GCM2, AES_256_GCM2");
  parser.add_long_opt("secret", &config.secret, "input secret when enable decryptionMode/option");
  parser.add_long_opt("salt", &config.salt, "input salt when enable decryptionMode/option");
  parser.add_long_opt("streamType", &config.streamType, "remote video stream type(0:STREAM_HIGH,1:STREAM_LOW), default is 0/option");
  parser.add_long_opt("enableCloudProxy", &config.enableCloudProxy, "enable cloud proxy, default is false/option");
  parser.add_long_opt("audioIndicationInterval", &config.audioIndicationInterval, "audio indication interval in ms, default is 0, means disable audio indication/option");
  parser.add_long_opt("subscribeVideoUids", &config.subscribeVideoUids, "video stream of specific uids. uids seperated by commas, like 1234,2345/option");
  parser.add_long_opt("subscribeAudioUids", &config.subscribeAudioUids, "audio stream of specific uids. uids sperated by commas, like 1234,2345 /option");
  parser.add_long_opt("getVideoFrame", &config.getVideoFrame, "default 0 (0: disable, 1:encoded video frame, eg:h.264,h.265, 2:yuv, 3:jpg frame, 4:jpg file");
  parser.add_long_opt("captureInterval", &config.captureInterval, "default 5 (Video snapshot interval (second)), min 1 sec");

  if (!parser.parse_opts(argc, argv) || config.appId.empty() || config.token.empty() || config.ChannelName.empty()) {
    std::ostringstream sout;
    parser.print_usage(argv[0], sout);
    std::cout << sout.str() << std::endl;
    return -1;
  }

  init_subscribe_uids(config);

  if(config.recordFileRootDir.empty()){
    config.recordFileRootDir = ".";
  }
  set_record_folder(config.recordFileRootDir, config.ChannelName);
  if (config.recordFileRootDir.back() != '/') {
    config.recordFileRootDir += '/';
  }

  std::string appLogPath = config.recordFileRootDir + "recorder_local.log";
  Logger::instance().open(appLogPath.c_str());
  AG_LOG(INFO, "config: ChannelName: %s, UserId: %s, isMixingEnabled: %d, idleLimitSec: %d, \
          recordFileRootDir: %s, autoSubscribe: %d, useStringUid: %d, subscribeVideoRegula: %s, subscribeAudioRegula: %s",
        config.ChannelName.c_str(), config.UserId.c_str(), config.isMix, config.idleLimitSec,
        config.recordFileRootDir.c_str(), config.autoSubscribe, config.useStringUid,
        config.subscribeVideoRegula.c_str(), config.subscribeAudioRegula.c_str());
  
  std::string logPath = config.recordFileRootDir + "agorasdk.log";
  auto service = createAgoraService();
  agora::base::AgoraServiceConfiguration service_config;
  service_config.enableAudioDevice = false;
  service_config.enableAudioProcessor = true;
  service_config.enableVideo = true;
  service_config.appId = config.appId.c_str();
  service_config.useStringUid = config.useStringUid;
  service_config.logConfig.filePath = logPath.c_str();
  service_config.logConfig.fileSizeInKB = 1024 * 100; // 100MB
  int ret = service->initialize(service_config);
  if(ret < 0) {
    AG_LOG(FATAL, "Failed to initialize Agora service, error code: %d", ret);
    Logger::instance().close();
    return -1;
  }
  if (config.enableCloudProxy) {
    auto agoraParameter = service->getAgoraParameter();
    agoraParameter->setBool("rtc.enable_proxy", true);
    AG_LOG(INFO, "set the Cloud_Proxy Open!");
  }

  service->getAgoraParameter()->setParameters("{\"rtc.audio.enable_user_silence_packet\":true}");
  
  agora::rtc::IMediaComponentFactory* factory = createAgoraMediaComponentFactory();
  agora::agora_refptr<agora::rtc::IAgoraMediaRtcRecorder> recorder =  factory->createMediaRtcRecorder();
  bool recordEncodedOnly = false;
  if(!config.isMix){
    recordEncodedOnly = true; // record encoded video only
  }
  recorder->initialize(service, config.isMix, recordEncodedOnly);
  
  std::unique_ptr<RecorderEventHandler> eventHandler{new RecorderEventHandler(recorder, config)};
  recorder->registerRecorderEventHandle(eventHandler.get());
  
  if(!config.decryptionMode.empty() && !config.secret.empty()) {
    agora::rtc::EncryptionConfig encryptConfig;
    if(config.decryptionMode == "AES_128_XTS") {
      encryptConfig.encryptionMode = agora::rtc::AES_128_XTS;
    } else if (config.decryptionMode == "AES_128_ECB") {
      encryptConfig.encryptionMode = agora::rtc::AES_128_ECB;
    } else if (config.decryptionMode == "AES_256_XTS") {
      encryptConfig.encryptionMode = agora::rtc::AES_256_XTS;
    } else if (config.decryptionMode == "SM4_128_ECB") {
      encryptConfig.encryptionMode = agora::rtc::SM4_128_ECB;
    } else if (config.decryptionMode == "AES_128_GCM") {
      encryptConfig.encryptionMode = agora::rtc::AES_128_GCM;
    } else if (config.decryptionMode == "AES_256_GCM") {
      encryptConfig.encryptionMode = agora::rtc::AES_256_GCM;
    } else if (config.decryptionMode == "AES_128_GCM2") {
      encryptConfig.encryptionMode = agora::rtc::AES_128_GCM2;
    } else if (config.decryptionMode == "AES_256_GCM2") {
      encryptConfig.encryptionMode = agora::rtc::AES_256_GCM2;
    } else {
      AG_LOG(ERROR, "Unsupported decryption mode: %s", config.decryptionMode.c_str());
      return -1;
    }
    encryptConfig.encryptionKey = config.secret.c_str();
    if(!config.salt.empty()){
      memcpy(encryptConfig.encryptionKdfSalt, config.salt.data(), 32);
    }
    recorder->enableEncryption(true, encryptConfig);
  }
  
  if(config.autoSubscribe){
    recorder->subscribeAllAudio();
    agora::rtc::VideoSubscriptionOptions options;
    if(config.isMix || config.getVideoFrame > 1){
      options.encodedFrameOnly = false;
    } else {
      options.encodedFrameOnly = true;
    }
    options.type = config.streamType == 0 ? agora::rtc::VIDEO_STREAM_HIGH : agora::rtc::VIDEO_STREAM_LOW;
    recorder->subscribeAllVideo(options);
  }

  std::unique_ptr<RecorderVideoFrameObserver> videoFrameObserver{new RecorderVideoFrameObserver()};
  agora::rtc::RecorderVideoFrameCaptureConfig videoFrameCaptureConfig;
  if(config.getVideoFrame != 0){
    videoFrameCaptureConfig.videoFrameType = static_cast<agora::rtc::VideoFrameCaptureType>(config.getVideoFrame-1);
    videoFrameCaptureConfig.jpgCaptureIntervalInSec = config.captureInterval;
    videoFrameCaptureConfig.jpgFileStorePath = config.recordFileRootDir.c_str();
    videoFrameCaptureConfig.observer = videoFrameObserver.get();
    recorder->enableRecorderVideoFrameCapture(true, videoFrameCaptureConfig);
  }

  if(config.audioIndicationInterval > 0) {
    recorder->setAudioVolumeIndicationParameters(config.audioIndicationInterval);
  }

  recorder->joinChannel(config.token.c_str(), config.ChannelName.c_str(), config.UserId.c_str());
  
  if(config.isMix){
    char fileName[512];
    char timeBuffer[80];
    Time2UTCStr(timeBuffer, 80);
    snprintf(fileName, 512,"%s.mp4", timeBuffer);
    std::string storagePath = config.recordFileRootDir + fileName;

    agora::media::MediaRecorderStreamType streamType = agora::media::STREAM_TYPE_BOTH;
    if(config.isAudioOnly) {
      streamType = agora::media::STREAM_TYPE_AUDIO;
    } else if(config.isVideoOnly) {
      streamType = agora::media::STREAM_TYPE_VIDEO;
    }

    agora::media::MediaRecorderConfiguration recorder_config;
    recorder_config.width = config.video.width;
    recorder_config.height = config.video.height;
    recorder_config.fps = config.video.fps;
    recorder_config.storagePath = storagePath.c_str();
    recorder_config.sample_rate = config.audio.sampleRate;
    recorder_config.channel_num = config.audio.numOfChannels;
    recorder_config.streamType = streamType;
    recorder_config.maxDurationMs = config.maxDuration * 1000;
    recorder->setRecorderConfig(recorder_config);
    recorder->startRecording();
  }

  int idleLimitMs = config.idleLimitSec * 1000;
  while (!g_bSignalStop) {
    usleep(100*1000);
    
    int userCount = eventHandler->getUidsCount();
    if (userCount == 0) {
      idleLimitMs -= 100;
      if (idleLimitMs <= 0) {
        AG_LOG(INFO, "No user joined for %d seconds, exiting...", config.idleLimitSec);
        break;
      }
    } else {
      idleLimitMs = config.idleLimitSec * 1000; // reset idle timer
    }   
  }

  if(config.autoSubscribe){
    recorder->unsubscribeAllAudio();
    recorder->unsubscribeAllVideo();
  }
  if(config.isMix){
    recorder->stopRecording();
  } else {
    std::set<std::string> uids = eventHandler->getUids();
    for(auto& uid : uids){
      recorder->stopSingleRecordingByUid(uid.c_str());
    }
  }

  if(config.getVideoFrame != 0){
    recorder->enableRecorderVideoFrameCapture(false, videoFrameCaptureConfig);
  }
  recorder->unregisterRecorderEventHandle(eventHandler.get());
  eventHandler = nullptr;
  recorder->leaveChannel();
  recorder = nullptr;
  service->release();
  Logger::instance().close();
  return 0;
}