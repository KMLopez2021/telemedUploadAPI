#include "recorderEventHandler.h"
#include "IAgoraMediaRtcRecorder.h"
#include <regex>
#include <string>
#include <string>
#include "log.h"
#include <ctime>
#include "time_util.h"

// layout
void RecorderEventHandler::setVideoMixLayout()
{
  if(uid_resolutions_.size()> 0 && uid_resolutions_.size() <= 17){
    agora::rtc::VideoMixingLayout layout;
    layout.canvasWidth = config_.video.width;
    layout.canvasHeight = config_.video.height;
    layout.canvasFps = config_.video.fps;
    layout.userLayoutConfigNum = uid_resolutions_.size();
    layout.backgroundColor = 0x000000;
    layout.backgroundImage = NULL;
    agora::rtc::UserMixerLayout use_layout[uid_resolutions_.size()];
    adjustBestFitVideoLayout(use_layout);
    layout.userLayoutConfigs = use_layout;
    recorder_->setVideoMixingLayout(layout);
  }
}

void scaleWidthHeight(int videoWidth, int videoHeight, int canvasWidth, int canvasHeight, int &scaledWidth, int &scaledHeight) 
{
  float videoAspectRatio = static_cast<float>(videoWidth) / videoHeight;
  float canvasAspectRatio = static_cast<float>(canvasWidth) / canvasHeight;

  if (videoAspectRatio > canvasAspectRatio) {
    scaledWidth = canvasWidth;
    scaledHeight = static_cast<int>(canvasWidth / videoAspectRatio);
  } else {
    scaledHeight = canvasHeight;
    scaledWidth = static_cast<int>(canvasHeight * videoAspectRatio);
  }
}

void RecorderEventHandler::adjustBestFitLayout_Square(agora::rtc::UserMixerLayout* regionList, int nSquare) 
{
  float viewWidth = static_cast<float>(1.f * 1.0/nSquare);
  float viewHEdge = static_cast<float>(1.f * 1.0/nSquare);
  int i = 0;
  for(const auto& uid : uid_resolutions_){
    float xIndex =static_cast<float>(i%nSquare);
    float yIndex = static_cast<float>(i/nSquare);
    regionList[i].userId = uid.first.c_str();

    int x = 1.f * 1.0/nSquare * xIndex * config_.video.width;
    int y = 1.f * 1.0/nSquare * yIndex * config_.video.height;
    int videoWidth = uid.second.width;
    int videoHeight = uid.second.height;
    int canvasWidth = viewWidth * config_.video.width;
    int canvasHeight = viewHEdge * config_.video.height;
    int scaledWidth, scaledHeight;
    scaleWidthHeight(videoWidth, videoHeight, canvasWidth, canvasHeight, scaledWidth, scaledHeight);
    regionList[i].config.x = x + (canvasWidth - scaledWidth) / 2;
    regionList[i].config.y = y + (canvasHeight - scaledHeight) / 2;
    regionList[i].config.width = scaledWidth;
    regionList[i].config.height = scaledHeight;

    i++;
  }
}

void RecorderEventHandler::adjustBestFitLayout_2(agora::rtc::UserMixerLayout* regionList) 
{
  int i = 0;
  for(const auto& uid : uid_resolutions_){
    regionList[i].userId = uid.first.c_str();
    
    int x = (((i+1)%2)?0:0.5) * config_.video.width;
    int y = 0;
    int videoWidth = uid.second.width;
    int videoHeight = uid.second.height;
    int canvasWidth = 0.5f * config_.video.width;
    int canvasHeight = 1.f * config_.video.height;
    int scaledWidth, scaledHeight;
    scaleWidthHeight(videoWidth, videoHeight, canvasWidth, canvasHeight, scaledWidth, scaledHeight);

    regionList[i].config.x = x + (canvasWidth - scaledWidth) / 2;
    regionList[i].config.y =  y + (canvasHeight - scaledHeight) / 2;
    regionList[i].config.width = scaledWidth;
    regionList[i].config.height = scaledHeight;
    i++;
  }
}

void RecorderEventHandler::adjustBestFitLayout_17(agora::rtc::UserMixerLayout* regionList) 
{
  int n = 5;
  float viewWidth = static_cast<float>(1.f * 1.0/n);
  float viewHEdge = static_cast<float>(1.f * 1.0/n);
  
  int i = 0;
  for(const auto& uid : uid_resolutions_){
    float xIndex = static_cast<float>(i%(n-1));
    float yIndex = static_cast<float>(i/(n-1));
    regionList[i].userId = uid.first.c_str();

    int x = ((i == 16) ? ((1-viewWidth)*(1.f/2) * 1.f) * config_.video.width : (0.5f * viewWidth + viewWidth * xIndex)) * config_.video.width;
    int y = (1.f * 1.0/n) * yIndex * config_.video.height;
    int videoWidth = uid.second.width;
    int videoHeight = uid.second.height;
    int canvasWidth = viewWidth * config_.video.width;
    int canvasHeight = viewHEdge * config_.video.height;
    int scaledWidth, scaledHeight;
    scaleWidthHeight(videoWidth, videoHeight, canvasWidth, canvasHeight, scaledWidth, scaledHeight);
    
    regionList[i].config.x = x + (canvasWidth - scaledWidth) / 2;
    regionList[i].config.y = y + (canvasHeight - scaledHeight) / 2;
    regionList[i].config.width = scaledWidth;
    regionList[i].config.height = scaledHeight;
    
    i++;
  }
}

void RecorderEventHandler::adjustBestFitVideoLayout(agora::rtc::UserMixerLayout* regionList) 
{
  if(uid_resolutions_.size() == 1) {
      adjustBestFitLayout_Square(regionList,1);
  }else if(uid_resolutions_.size() == 2) {
      adjustBestFitLayout_2(regionList);
  }else if( 2 < uid_resolutions_.size() && uid_resolutions_.size() <=4) {
      adjustBestFitLayout_Square(regionList,2);
  }else if(5<=uid_resolutions_.size() && uid_resolutions_.size() <=9) {
      adjustBestFitLayout_Square(regionList,3);
  }else if(10<=uid_resolutions_.size() && uid_resolutions_.size() <=16) {
      adjustBestFitLayout_Square(regionList,4);
  }else if(uid_resolutions_.size() ==17) {
      adjustBestFitLayout_17(regionList);
  }else {
      AG_LOG(ERROR, "adjustBestFitVideoLayout is more than 17 users");
  }
}

//  IAgoraMediaRtcRecorderEventHandler

void RecorderEventHandler::onConnected(const char *channelId, agora::user_id_t uid)
{
  AG_LOG(INFO, "onConnected, channelId: %s, uid: %s", channelId, uid);
}

void RecorderEventHandler::onDisconnected(const char *channelId, agora::user_id_t uid, agora::rtc::CONNECTION_CHANGED_REASON_TYPE reason)
{
  AG_LOG(INFO, "onDisconnected, channelId: %s, uid: %s, reason: %d", channelId, uid, reason);
}

void RecorderEventHandler::onReconnected(const char *channelId, agora::user_id_t uid,agora::rtc::CONNECTION_CHANGED_REASON_TYPE reason)
{
  AG_LOG(INFO, "onReconnected, channelId: %s, uid: %s, reason: %d", channelId, uid, reason);
}

void RecorderEventHandler::onConnectionLost(const char *channelId, agora::user_id_t uid)
{
  AG_LOG(ERROR, "onConnectionLost, channelId: %s, uid: %s", channelId, uid);
}

bool RecorderEventHandler::isSubscribeStream(agora::user_id_t uid, bool isAudio) 
{
  auto sub_match = [](const std::string &in_str, const std::string &regex) {
    const std::regex pieces_regex(regex);
    std::smatch pieces_match;
    return std::regex_match(in_str, pieces_match, pieces_regex);
  };

  const std::string *subscribeRegula = isAudio ? &config_.subscribeAudioRegula : &config_.subscribeVideoRegula;
  return sub_match(uid, *subscribeRegula);
}

void RecorderEventHandler::onUserJoined(const char *channelId,agora::user_id_t uid)
{
  AG_LOG(INFO, "onUserJoined, channelId: %s, uid: %s", channelId, uid);
  {
    std::lock_guard<std::mutex> lock(mtx);
    uids_.insert(uid);
  }

  agora::media::MediaRecorderStreamType streamType = agora::media::STREAM_TYPE_AUDIO;
  if(!config_.autoSubscribe){
    bool subAudio = false;
    bool subVideo = false;
    if(!config_.isVideoOnly && 
        (config_.subscribeAudioRegula.empty() || 
        isSubscribeStream(uid, true) || 
        config_.subscribeAudioUserList.find(uid) != config_.subscribeAudioUserList.end())){
      
      recorder_->subscribeAudio(uid);
      subAudio = true;
    }
    if(!config_.isAudioOnly && 
      (config_.subscribeVideoRegula.empty() || 
      isSubscribeStream(uid, false) || 
      config_.subscribeVideoUserList.find(uid) != config_.subscribeVideoUserList.end())){

      agora::rtc::VideoSubscriptionOptions options;
      if(config_.isMix || config_.getVideoFrame > 1){
        options.encodedFrameOnly = false;
      } else {
        options.encodedFrameOnly = true;
      }
      options.type = config_.streamType == 0 ? agora::rtc::VIDEO_STREAM_HIGH : agora::rtc::VIDEO_STREAM_LOW;
      recorder_->subscribeVideo(uid, options);
      subVideo = true;
    }
    if(!subAudio && !subVideo){
      AG_LOG(INFO, "onUserJoined, uid: %s, not subscribe audio and video", uid);
      return;
    } else if(subAudio && subVideo){
      streamType = agora::media::STREAM_TYPE_BOTH;
    } else if(subAudio){
      streamType = agora::media::STREAM_TYPE_AUDIO;
    } else {
      streamType = agora::media::STREAM_TYPE_VIDEO;
    }
  } else {
    if(config_.isAudioOnly) {
      streamType = agora::media::STREAM_TYPE_AUDIO;
    } else if(config_.isVideoOnly) {
      streamType = agora::media::STREAM_TYPE_VIDEO;
    } else {
      streamType = agora::media::STREAM_TYPE_BOTH;
    }
  }
  AG_LOG(INFO, "uid: %s, recorder streamType: %d", uid, streamType);

  if(!config_.isMix){
    agora::media::MediaRecorderConfiguration config;
    config.fps = config_.video.fps;
    config.width = config_.video.width;
    config.height = config_.video.height;
    config.channel_num = config_.audio.numOfChannels;
    config.sample_rate = config_.audio.sampleRate;
    
    char timeBuffer[80];
    Time2UTCStr(timeBuffer, 80);
    std::string storagePath = config_.recordFileRootDir + std::string(uid) + "_" + timeBuffer + ".mp4";
    config.storagePath = storagePath.c_str();
    config.streamType = streamType;
    config.maxDurationMs = config_.maxDuration * 1000;
    recorder_->setRecorderConfigByUid(config, uid);
    recorder_->startSingleRecordingByUid(uid);
  }
}

void RecorderEventHandler::onUserLeft(const char *channelId,agora::user_id_t uid, agora::rtc::USER_OFFLINE_REASON_TYPE reason)
{
  AG_LOG(INFO, "onUserLeft, channelId: %s, uid: %s, reason: %d", channelId, uid, reason);
  
  {
    std::lock_guard<std::mutex> lock(mtx);
    uids_.erase(uid);
  }

  uid_resolutions_.erase(uid);
  if(config_.isMix){
    setVideoMixLayout();
  } else {
    recorder_->stopSingleRecordingByUid(uid);
  }
}

void RecorderEventHandler::onFirstRemoteVideoDecoded(const char *channelId,agora::user_id_t userId, int width, int height, int elapsed)
{
  AG_LOG(INFO,"onFirstRemoteVideoDecoded, channelId: %s, userId: %s, width: %d, height: %d, elapsed: %d", channelId, userId, width, height,elapsed);
  uid_resolutions_[userId] = {width, height};
  if(config_.isMix){
    setVideoMixLayout();
  } 
}

void RecorderEventHandler::onFirstRemoteAudioDecoded(const char *channelId,agora::user_id_t userId, int elapsed)
{
  AG_LOG(INFO,"onFirstRemoteAudioDecoded, channelId: %s, userId: %s, elapsed: %d", channelId, userId, elapsed);
}

void RecorderEventHandler::onRecorderStateChanged(const char *channelId, agora::user_id_t userId, agora::media::RecorderState state, agora::media::RecorderReasonCode reason, const char* filename) 
{
  AG_LOG(INFO, "onRecorderStateChanged, channelId: %s, userId: %s, state: %d, fileName: %s, reason: %d", channelId, userId, state, filename, reason);
}

void RecorderEventHandler::onRecorderInfoUpdated(const char *channelId, agora::user_id_t userId, const agora::media::RecorderInfo &info) 
{
  AG_LOG(INFO, "onRecorderInfoUpdated, channelId: %s, userId: %s, fileName: %s, duration: %d, fileSize: %d", 
    channelId, userId, info.fileName, info.durationMs, info.fileSize);
}

void RecorderEventHandler::onUserVideoStateChanged(const char *channelId,agora::user_id_t userId, agora::rtc::REMOTE_VIDEO_STATE state, agora::rtc::REMOTE_VIDEO_STATE_REASON reason, int elapsed)
{
  AG_LOG(INFO, "onUserVideoStateChanged, channelId: %s, userId: %s, state: %d, reason: %d, elapsed: %d", channelId, userId, state, reason, elapsed);
}

void RecorderEventHandler::onUserAudioStateChanged(const char *channelId,agora::user_id_t userId, agora::rtc::REMOTE_AUDIO_STATE state, agora::rtc::REMOTE_AUDIO_STATE_REASON reason, int elapsed)
{
  AG_LOG(INFO, "onUserAudioStateChanged, channelId: %s, userId: %s, state: %d, reason: %d, elapsed: %d", channelId, userId, state, reason, elapsed);
}


// IRecorderVideoFrameObserver
void RecorderVideoFrameObserver::onYuvFrameCaptured(const char* channelId, agora::user_id_t userId, const agora::media::base::VideoFrame *frame)
{
  AG_LOG(INFO, "onRecorderYuvFrameCapture: channelId=%s, userId=%s, width=%d, height=%d",channelId, userId, frame->width, frame->height);
}

void RecorderVideoFrameObserver::onEncodedFrameReceived(const char* channelId, agora::user_id_t userId, const uint8_t* imageBuffer, size_t length,agora::rtc::EncodedVideoFrameInfo videoEncodedFrameInfo)
{
  AG_LOG(INFO, "onRecorderEncodedFrameCapture: channelId=%s, userId=%s, length=%zu, codecType=%d", channelId, userId, length, videoEncodedFrameInfo.codecType);
}

void RecorderVideoFrameObserver::onJPGFileSaved(const char* channelId, agora::user_id_t userId, const char* jpgFilePath) 
{
  AG_LOG(INFO, "onRecorderJpgFileCapture: channelId=%s, userId=%s, jpgFilePath=%s", channelId, userId, jpgFilePath);
}