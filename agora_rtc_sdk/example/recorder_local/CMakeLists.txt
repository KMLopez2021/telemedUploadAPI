cmake_minimum_required(VERSION 2.4)
project(DefaultSamples)

include_directories(
  "${PROJECT_SOURCE_DIR}"
)

# Build sample_receive_mixed_audio
file(GLOB SAMPLE_RECORDER_FILES
     "${PROJECT_SOURCE_DIR}/main.cpp"
     "${PROJECT_SOURCE_DIR}/opt_parser.cpp"
     "${PROJECT_SOURCE_DIR}/recorderEventHandler.cpp"
     "${PROJECT_SOURCE_DIR}/time_util.cpp")
add_executable(recorder_local ${SAMPLE_RECORDER_FILES})