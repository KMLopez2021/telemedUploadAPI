cmake_minimum_required(VERSION 2.4)
project(DefaultSamples)

include_directories(
  "${PROJECT_SOURCE_DIR}"
  "/var/www/telemedUploadAPI/agora_rtc_sdk/agora_sdk/include"
)

# Build sample_receive_mixed_audio
file(GLOB SAMPLE_RECORDER_FILES
     "${PROJECT_SOURCE_DIR}/sample_recorder.cpp"
     "${PROJECT_SOURCE_DIR}/recorderEventHandler.cpp"
     "${PROJECT_SOURCE_DIR}/parser/parser.cpp"
     "${PROJECT_SOURCE_DIR}/parser/cJSON.c")
add_executable(sample_recorder ${SAMPLE_RECORDER_FILES})

# Link with the Agora SDK library
target_link_libraries(sample_recorder
    "/var/www/telemedUploadAPI/agora_rtc_sdk/agora_sdk/libagora_rtc_sdk.so"
)