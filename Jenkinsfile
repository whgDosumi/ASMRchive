pipeline {
    agent any
    options {
        throttleJobProperty(
        categories: ['ASMRchive'],
        throttleEnabled: true,
        throttleOption: 'category'
        )
        buildDiscarder(logRotator(numToKeepStr: '3'))
    }
}